// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import CourseElement from './course/element';
import BlockElement from './element';
import SELECTORS from './selectors';

export default class EventHandler {
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
     * @param {boolean} canBackupUserdata
     * @param {boolean} canAnonymizeUserdata
     * @param {boolean} canBackup
     * @param {boolean} showCopyToCartIcon
     * @return {{course: CourseElement, block: BlockElement, queue: Object}}
     */
    onLoad(canBackupUserdata, canAnonymizeUserdata, canBackup, showCopyToCartIcon) {
        const component = this.#baseFactory.component();
        const courseId = component.reactive.state.course.id;
        const queue = this.#baseFactory.block().queue();
        const block = new BlockElement(this.#baseFactory, component, queue, courseId);
        const course = new CourseElement(
            this.#baseFactory,
            block,
            canBackup,
            showCopyToCartIcon,
            canBackupUserdata,
            canAnonymizeUserdata
        );

        block.bindDropzone();
        block.bindItemActions();
        block.bindClearCart();

        const cartItems = block.getElement(SELECTORS.CART_ITEMS);
        queue.initSortable(cartItems, () => queue.elements(courseId));
        queue.updateCartControls(queue.elements(courseId));

        return {course, block, queue};
    }
}
