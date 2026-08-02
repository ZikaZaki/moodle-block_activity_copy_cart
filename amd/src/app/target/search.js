// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import SELECTORS from './selectors';

// Matches the capability the tree itself and courses_tree::filter() already require of a copy target.
const REQUIRED_CAPABILITY = 'moodle/restore:restoretargetimport';
const SEARCH_DEBOUNCE_MS = 300;
const RESULTS_PER_PAGE = 25;

export default class Search {
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
     * @param {string} query
     * @param {number} sourceCourseId
     * @param {HTMLElement} resultsList
     * @param {HTMLElement} noResults
     * @return {Promise}
     */
    async #searchCourses(query, sourceCourseId, resultsList, noResults) {
        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        try {
            const {courses: allCourses} = await ajax.call('core_course_search_courses', {
                criterianame: 'search',
                criteriavalue: query,
                page: 0,
                perpage: RESULTS_PER_PAGE,
                requiredcapabilities: [REQUIRED_CAPABILITY],
            });
            const courses = allCourses.filter((course) => course.id !== sourceCourseId);

            resultsList.innerHTML = '';
            noResults.hidden = courses.length !== 0;

            const rendered = await Promise.all(courses.map(
                (course) => template.renderTemplate('block_activity_copy_cart/target/course', {
                    id: course.id,
                    fullname: course.fullname,
                })
            ));
            rendered.forEach(({html, js}) => template.appendNodeContents(resultsList, html, js));
            return null;
        } catch (error) {
            ajax.notifyException(error);
            return null;
        }
    }

    /**
     * Wires the search input inside the tree container, swapping between the
     * browse tree and a flat results list as the teacher types.
     * @param {HTMLElement} container - [data-region="target-tree"]
     * @param {number} sourceCourseId
     * @param {Object} state - The page's selection state (state.js's TreeState)
     */
    init(container, sourceCourseId, state) {
        const input = container.querySelector(SELECTORS.TREE_SEARCH_INPUT);
        const treeRoot = container.querySelector(SELECTORS.TREE_ROOT);
        const resultsRegion = container.querySelector(SELECTORS.TREE_SEARCH_RESULTS);
        const resultsList = container.querySelector(SELECTORS.TREE_SEARCH_RESULTS_LIST);
        const noResults = container.querySelector(SELECTORS.TREE_SEARCH_NO_RESULTS);
        if (!input || !treeRoot || !resultsRegion || !resultsList || !noResults) {
            return;
        }

        let debounceHandle = null;

        input.addEventListener('input', () => {
            window.clearTimeout(debounceHandle);
            const query = input.value.trim();

            if (query === '') {
                resultsRegion.hidden = true;
                treeRoot.hidden = false;
                return;
            }

            treeRoot.hidden = true;
            resultsRegion.hidden = false;
            debounceHandle = window.setTimeout(
                () => this.#searchCourses(query, sourceCourseId, resultsList, noResults),
                SEARCH_DEBOUNCE_MS
            );
        });

        resultsList.addEventListener('change', (event) => {
            const checkbox = event.target.closest(SELECTORS.TREE_COURSE_CHECKBOX);
            if (checkbox) {
                state.toggleCourse(parseInt(checkbox.dataset.courseId, 10), checkbox.checked);
            }
        });
    }
}
