// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import SELECTORS from './selectors';

// Categories whose children have already been fetched at least once. Stays
// module-level (not instance state): it's a page-lifetime cache, and Node
// may be constructed more than once over the page's life.
const loadedCategoryIds = new Set();

/**
 * @param {HTMLLIElement} categoryNode
 * @param {HTMLButtonElement} toggle
 * @param {HTMLElement} childrenContainer
 * @param {boolean} expanded
 */
function setExpanded(categoryNode, toggle, childrenContainer, expanded) {
    categoryNode.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    childrenContainer.hidden = !expanded;

    const icon = toggle.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-caret-down', expanded);
        icon.classList.toggle('fa-caret-right', !expanded);
    }
}

export default class Node {
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
     * Appends the rendered category/course nodes, or a "nothing here" message
     * if the category turned out to have no eligible children once
     * capability/visibility filtering was applied server-side.
     * @param {HTMLElement} childrenContainer
     * @param {Array<{html: string, js: string}>} rendered
     * @return {Promise}
     */
    async #renderChildren(childrenContainer, rendered) {
        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        if (rendered.length === 0) {
            const str = await ajax.getString('nocoursesavailable', 'block_activity_copy_cart');
            childrenContainer.insertAdjacentHTML('beforeend', `<li class="text-muted small">${str}</li>`);
            return null;
        }
        rendered.forEach(({html, js}) => template.appendNodeContents(childrenContainer, html, js));
        return null;
    }

    /**
     * Expands a category node if it isn't already - unlike toggleCategoryNode,
     * never collapses it, so it's safe to call repeatedly (e.g. walking down an
     * ancestor path while restoring a saved selection - see state.js's
     * TreeState.restoreFromPaths()) without undoing a node the user already had open.
     * @param {HTMLLIElement} categoryNode - [data-region="category-node"]
     * @param {number} sourceCourseId
     * @param {Object} state - The page's selection state (state.js's TreeState)
     * @return {Promise}
     */
    async ensureExpanded(categoryNode, sourceCourseId, state) {
        const toggle = categoryNode.querySelector(SELECTORS.TREE_TOGGLE);
        const childrenContainer = categoryNode.querySelector(`:scope > ${SELECTORS.TREE_CATEGORY_CHILDREN}`);
        if (!toggle || !childrenContainer) {
            return null;
        }
        if (toggle.getAttribute('aria-expanded') === 'true') {
            return null;
        }

        const categoryId = parseInt(categoryNode.dataset.categoryId, 10);
        if (loadedCategoryIds.has(categoryId)) {
            setExpanded(categoryNode, toggle, childrenContainer, true);
            return null;
        }

        toggle.disabled = true;

        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        try {
            const {categories, courses} = await ajax.call(
                'block_activity_copy_cart_get_target_tree_node',
                {sourcecourseid: sourceCourseId, categoryid: categoryId}
            );
            // allSettled rather than all: one bad category/course template shouldn't discard an
            // otherwise-successful batch - render everything that did succeed and skip the rest.
            const settled = await Promise.allSettled([
                ...categories.map((category) => template.renderTemplate('block_activity_copy_cart/target/category', category)),
                ...courses.map((course) => template.renderTemplate('block_activity_copy_cart/target/course', course)),
            ]);
            const rendered = settled
                .filter((result) => result.status === 'fulfilled')
                .map((result) => result.value);
            await this.#renderChildren(childrenContainer, rendered);

            loadedCategoryIds.add(categoryId);
            state.applyInheritedSelection(childrenContainer);
            setExpanded(categoryNode, toggle, childrenContainer, true);
            toggle.disabled = false;
            return null;
        } catch (error) {
            toggle.disabled = false;
            ajax.notifyException(error);
            return null;
        }
    }

    /**
     * Expands or collapses a category node in response to a user click,
     * fetching its children on first expand.
     * @param {HTMLLIElement} categoryNode - [data-region="category-node"]
     * @param {number} sourceCourseId
     * @param {Object} state - The page's selection state (state.js's TreeState)
     * @return {Promise|undefined}
     */
    toggleCategoryNode(categoryNode, sourceCourseId, state) {
        const toggle = categoryNode.querySelector(SELECTORS.TREE_TOGGLE);
        if (toggle?.getAttribute('aria-expanded') === 'true') {
            const childrenContainer = categoryNode.querySelector(`:scope > ${SELECTORS.TREE_CATEGORY_CHILDREN}`);
            if (childrenContainer) {
                setExpanded(categoryNode, toggle, childrenContainer, false);
            }
            return undefined;
        }
        return this.ensureExpanded(categoryNode, sourceCourseId, state);
    }
}
