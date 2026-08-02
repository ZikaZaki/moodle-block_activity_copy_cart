import BaseFactory from '../factory';
import Node from './node';
import Search from './search';
import SELECTORS from './selectors';
import {TreeState} from './state';

export default class Factory {
    /**
     * @type {BaseFactory}
     */
    #baseFactory;

    /**
     * @param {BaseFactory} baseFactory
     */
    constructor(baseFactory) {
        this.#baseFactory = baseFactory;
    }

    /**
     * @param {HTMLFormElement} form
     * @param {string} courseFieldId
     * @param {string} categoryFieldId
     * @param {number} sourceCourseId
     * @returns {TreeState}
     */
    state(form, courseFieldId, categoryFieldId, sourceCourseId) {
        return new TreeState(this.#baseFactory, form, courseFieldId, categoryFieldId, sourceCourseId);
    }

    /**
     * @returns {Node}
     */
    node() {
        return new Node(this.#baseFactory);
    }

    /**
     * @returns {Search}
     */
    search() {
        return new Search(this.#baseFactory);
    }

    /**
     * Static initialization called by Moodle PHP (classes/form/target_courses_form.php).
     * @param {string} courseFieldId - DOM id of the hidden targetcourseids input
     * @param {string} categoryFieldId - DOM id of the hidden targetcategoryids input
     * @param {number} sourceCourseId - The cart's source course id, excluded everywhere
     * @param {{courses: Array, categories: Array}} restorePaths - See courses_tree::restore_paths(), empty arrays if nothing saved
     */
    static init(courseFieldId, categoryFieldId, sourceCourseId, restorePaths) {
        const container = document.querySelector(SELECTORS.TREE_CONTAINER);
        if (!container) {
            return;
        }

        const form = container.closest('form');
        const baseFactory = BaseFactory.make(); // No reactive component on this page.
        const target = new this(baseFactory);
        const state = target.state(form, courseFieldId, categoryFieldId, sourceCourseId);
        const node = target.node();

        container.addEventListener('click', (event) => {
            const toggle = event.target.closest(SELECTORS.TREE_TOGGLE);
            if (!toggle) {
                return;
            }
            event.preventDefault();
            node.toggleCategoryNode(toggle.closest(SELECTORS.TREE_CATEGORY_NODE), sourceCourseId, state);
        });

        container.addEventListener('change', (event) => {
            const checkbox = event.target.closest(SELECTORS.TREE_CHECKBOX);
            if (!checkbox || checkbox.disabled) {
                return;
            }

            if (checkbox.matches(SELECTORS.TREE_CATEGORY_CHECKBOX)) {
                const categoryNode = checkbox.closest(SELECTORS.TREE_CATEGORY_NODE);
                state.toggleCategory(categoryNode, parseInt(checkbox.dataset.categoryId, 10), checkbox.checked);
            } else if (checkbox.matches(SELECTORS.TREE_COURSE_CHECKBOX)) {
                state.toggleCourse(parseInt(checkbox.dataset.courseId, 10), checkbox.checked);
            }
        });

        target.search().init(container, sourceCourseId, state);

        if (restorePaths && (restorePaths.courses.length || restorePaths.categories.length)) {
            state.restoreFromPaths(restorePaths).catch((error) => baseFactory.moodle().ajax().notifyException(error));
        }
    }
}
