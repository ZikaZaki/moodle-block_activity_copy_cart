// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import Node from './node';
import SELECTORS from './selectors';
import {setHiddenInput, debounce} from '../../lib/helpers';

const AUTOSAVE_DEBOUNCE_MS = 500;

/**
 * The target-selection page's course/category tree selection state.
 */
export class TreeState {

    /**
     * @param {BaseFactory} baseFactory
     * @param {HTMLFormElement} form
     * @param {string} courseFieldId - DOM id of the hidden targetcourseids input
     * @param {string} categoryFieldId - DOM id of the hidden targetcategoryids input
     * @param {number} sourceCourseId - The cart's source course id, keys the autosaved draft
     */
    constructor(baseFactory, form, courseFieldId, categoryFieldId, sourceCourseId) {
        this.baseFactory = baseFactory;
        this.form = form;
        this.courseFieldId = courseFieldId;
        this.categoryFieldId = categoryFieldId;
        this.sourceCourseId = sourceCourseId;
        this.selectedCourseIds = new Set(TreeState._parseField(courseFieldId));
        this.selectedCategoryIds = new Set(TreeState._parseField(categoryFieldId));
        // Chains autosave calls so a second one never starts before the first resolves -
        // otherwise two overlapping requests' responses can land out of order and let an
        // older selection silently overwrite a newer one server-side.
        this._pendingAutosave = Promise.resolve();
        this._scheduleAutosave = debounce(() => this._autosave(), AUTOSAVE_DEBOUNCE_MS);
    }

    /**
     * @param {string} fieldId - DOM id of a hidden PARAM_SEQUENCE input
     * @return {number[]}
     */
    static _parseField(fieldId) {
        const value = document.getElementById(fieldId)?.value ?? '';
        return value === '' ? [] : value.split(',').map((id) => parseInt(id, 10));
    }

    /**
     * Toggles an individually picked course (tree leaf or search result).
     * @param {number} courseId
     * @param {boolean} checked
     */
    toggleCourse(courseId, checked) {
        if (checked) {
            this.selectedCourseIds.add(courseId);
        } else {
            this.selectedCourseIds.delete(courseId);
        }
        this._sync();
    }

    /**
     * Toggles a "select this whole category" pick, cascading the visual
     * checked/disabled state to whatever descendants are currently rendered.
     * @param {HTMLLIElement} categoryNode - The category's [data-region="category-node"] element
     * @param {number} categoryId
     * @param {boolean} checked
     */
    toggleCategory(categoryNode, categoryId, checked) {
        if (checked) {
            this.selectedCategoryIds.add(categoryId);
            categoryNode.dataset.selectedAll = '1';
        } else {
            this.selectedCategoryIds.delete(categoryId);
            delete categoryNode.dataset.selectedAll;
        }

        this._cascade(categoryNode, checked);
        this._sync();
    }

    /**
     * Called right after inserting a lazily-fetched batch of child nodes, so
     * a category expanded *after* an ancestor was already "select all"'d
     * starts its children pre-checked and disabled instead of looking
     * unselected until the ancestor happens to be re-toggled.
     * @param {HTMLElement} childrenContainer - The just-populated [data-region="category-children"] element
     */
    applyInheritedSelection(childrenContainer) {
        if (!childrenContainer.closest('[data-selected-all="1"]')) {
            return;
        }
        childrenContainer.querySelectorAll(SELECTORS.TREE_CHECKBOX).forEach((checkbox) => {
            checkbox.checked = true;
            checkbox.disabled = true;
        });
    }

    /**
     * Replays a previously autosaved selection (already seeded into
     * selectedCourseIds/selectedCategoryIds by the constructor - submitting
     * without touching anything already works) onto the *visible* tree: for
     * each pick, expands its ancestor category path and checks its box.
     * @param {{courses: Array<{id: number, path: number[]}>, categories: Array<{id: number, path: number[]}>}} restorePaths -
     *   Built by classes/app/target/courses_tree.php::restore_paths()
     * @return {Promise}
     */
    async restoreFromPaths(restorePaths) {
        for (const {id, path} of restorePaths.categories) {
            if (!await this._expandPath(path)) {
                continue;
            }
            const categoryNode = TreeState._findCategoryNode(id);
            const checkbox = categoryNode?.querySelector(SELECTORS.TREE_CATEGORY_CHECKBOX);
            if (checkbox && !checkbox.disabled && !checkbox.checked) {
                checkbox.checked = true;
                this.toggleCategory(categoryNode, id, true);
            }
        }

        for (const {id, path} of restorePaths.courses) {
            if (!await this._expandPath(path)) {
                continue;
            }
            const checkbox = TreeState._findCourseNode(id)?.querySelector(SELECTORS.TREE_COURSE_CHECKBOX);
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = true;
            }
        }
    }

    /**
     * Sequentially expands each category id in a top-down ancestor path so
     * the node at the end of it exists in the DOM to act on.
     * @param {number[]} path
     * @return {Promise<boolean>} False if a node in the path couldn't be found (e.g. deleted since being saved)
     */
    async _expandPath(path) {
        const node = new Node(this.baseFactory);
        for (const categoryId of path) {
            const categoryNode = TreeState._findCategoryNode(categoryId);
            if (!categoryNode) {
                return false;
            }
            await node.ensureExpanded(categoryNode, this.sourceCourseId, this);
        }
        return true;
    }

    /**
     * @param {number} categoryId
     * @return {HTMLElement|null}
     */
    static _findCategoryNode(categoryId) {
        return document.querySelector(`${SELECTORS.TREE_CATEGORY_NODE}[data-category-id="${categoryId}"]`);
    }

    /**
     * @param {number} courseId
     * @return {HTMLElement|null}
     */
    static _findCourseNode(courseId) {
        return document.querySelector(`${SELECTORS.TREE_COURSE_NODE}[data-course-id="${courseId}"]`);
    }

    /**
     * Disables+checks (or re-enables+unchecks) every course/category
     * checkbox currently rendered under a category node, dropping their ids
     * from the selection sets - they're superseded by the ancestor pick.
     * @param {HTMLLIElement} categoryNode
     * @param {boolean} selectedAll
     */
    _cascade(categoryNode, selectedAll) {
        const childrenContainer = categoryNode.querySelector(`:scope > ${SELECTORS.TREE_CATEGORY_CHILDREN}`);
        if (!childrenContainer) {
            return;
        }

        childrenContainer.querySelectorAll(SELECTORS.TREE_COURSE_CHECKBOX).forEach((checkbox) => {
            this.selectedCourseIds.delete(parseInt(checkbox.dataset.courseId, 10));
            checkbox.checked = selectedAll;
            checkbox.disabled = selectedAll;
        });

        childrenContainer.querySelectorAll(SELECTORS.TREE_CATEGORY_CHECKBOX).forEach((checkbox) => {
            this.selectedCategoryIds.delete(parseInt(checkbox.dataset.categoryId, 10));
            delete checkbox.closest(SELECTORS.TREE_CATEGORY_NODE).dataset.selectedAll;
            checkbox.checked = selectedAll;
            checkbox.disabled = selectedAll;
        });
    }

    /**
     * Writes the current selection back to the two hidden form fields, and
     * schedules an autosave of the same selection to the session draft.
     */
    _sync() {
        setHiddenInput(this.form, 'targetcourseids', Array.from(this.selectedCourseIds).join(','), this.courseFieldId);
        setHiddenInput(this.form, 'targetcategoryids', Array.from(this.selectedCategoryIds).join(','), this.categoryFieldId);
        this._scheduleAutosave();
    }

    /**
     * Posts the current selection to the session draft - only ever called
     * through this._scheduleAutosave (built in the constructor), so
     * rapid-fire toggling (e.g. a "select all" cascade over a large
     * category) autosaves once, not once per checkbox.
     */
    _autosave() {
        this._pendingAutosave = this._pendingAutosave.then(() => {
            const ajax = this.baseFactory.moodle().ajax();
            return ajax.call('block_activity_copy_cart_save_target_courses', {
                sourcecourseid: this.sourceCourseId,
                courseids: Array.from(this.selectedCourseIds),
                categoryids: Array.from(this.selectedCategoryIds),
            }).catch((error) => ajax.notifyException(error));
        });
        return this._pendingAutosave;
    }
}
