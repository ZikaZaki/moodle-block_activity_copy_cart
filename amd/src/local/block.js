import BaseFactory from "../app/factory";
import {BaseComponent} from 'core/reactive';
import {getCurrentCourseEditor} from "core_courseformat/courseeditor";
import SELECTORS from "../app/block/selectors";

export default class Block extends BaseComponent {
    /**
     * @type {CourseElement}
     */
    course;

    /**
     * @type {BlockElement}
     */
    block;

    /**
     * @type {Object}
     */
    queue;

    /**
     * Constructor hook.
     * @param {Object} descriptor
     */
    create(descriptor) {
        // Optional component name for debugging.
        this.name = 'activitycopycart_block';
        // Default query selectors.
        this.selectors = {};

        this.canBackupUserdata = descriptor.canBackupUserdata ?? false;
        this.canAnonymizeUserdata = descriptor.canAnonymizeUserdata ?? false;
        this.canBackup = descriptor.canBackup ?? false;
        this.showCopyToCartIcon = descriptor.showCopyToCartIcon ?? false;
    }

    /**
     * Static method to create a component instance from the mustache template.
     *
     * @param {String} target - DOM id of the block's root element
     * @param {Boolean} canBackupUserdata
     * @param {Boolean} canAnonymizeUserdata
     * @param {Boolean} canBackup
     * @param {Boolean} showCopyToCartIcon
     */
    static init(target, canBackupUserdata, canAnonymizeUserdata, canBackup, showCopyToCartIcon) {
        return new this({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            canBackupUserdata,
            canAnonymizeUserdata,
            canBackup,
            showCopyToCartIcon
        });
    }

    /**
     * Initial state ready method: wires up the cart (dropzone, item actions,
     * clear button, sortable reorder) and the course-page button feature,
     * then gives every already-rendered course module a chance at the button.
     */
    stateReady() {
        this.baseFactory = BaseFactory.make(this);
        const {course, block, queue} = this.baseFactory.block().eventHandler().onLoad(
            this.canBackupUserdata,
            this.canAnonymizeUserdata,
            this.canBackup,
            this.showCopyToCartIcon
        );

        this.course = course;
        this.block = block;
        this.queue = queue;
        // Bind the delegated click listener for all "add to copy cart" buttons, including those that will be added later.
        this.course.bindAddToCartButtons();

        const courseContent = document.querySelector(SELECTORS.COURSE_CONTENT);
        if (courseContent) {
            const courseModuleElements = courseContent.querySelectorAll(SELECTORS.COURSE_MODULE_ITEM);
            courseModuleElements.forEach(courseModuleElement => {
                const courseModule = this.reactive.state.cm.get(courseModuleElement.dataset.id);
                this.course.refreshCourseModule({element: courseModule});
            });
        }
    }

    /**
     * Component watchers. Every handler is wrapped in an arrow function:
     * core/reactive invokes watcher handlers with `this` bound to the
     * registered component (this Block instance), so a bare method
     * reference to a CourseElement/BlockElement method would silently run
     * with the wrong `this`.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return [
            {watch: `cm.dragging:created`, handler: (args) => this.block.onDraggingCourseModule(args)},
            {watch: `cm.dragging:updated`, handler: (args) => this.block.onDraggingCourseModule(args)},
            {watch: `cm:created`, handler: (args) => this.course.refreshCourseModule(args)},
            {watch: `cm:updated`, handler: (args) => this.course.refreshCourseModule(args)},
        ];
    }
}
