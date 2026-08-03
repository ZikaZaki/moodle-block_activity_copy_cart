// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import SELECTORS from './selectors';

export default class BlockElement {
    /**
     * @type {BaseFactory}
     */
    #baseFactory;

    /**
     * @type {Object} The reactive Block component instance
     */
    #component;

    /**
     * @type {Object} The cart's Queue (constructed once by EventHandler.onLoad())
     */
    #queue;

    /**
     * @type {number}
     */
    #courseId;

    /**
     * Holds the ID of the activity currently being dragged.
     * @type {?number}
     */
    draggedCmId = null;

    /**
     * Handle for the pending "clear draggedCmId" timeout scheduled by setDraggedCourseModuleId(),
     * so a new drag starting within that window can cancel it instead of letting it null out a
     * currently-in-progress drag/drop.
     * @type {?number}
     */
    #clearDragTimeout = null;

    /**
     * @param {BaseFactory} baseFactory
     * @param {Object} component - The reactive Block instance
     * @param {Object} queue - The cart's Queue
     * @param {number} courseId - The course this block instance lives on
     */
    constructor(baseFactory, component, queue, courseId) {
        this.#baseFactory = baseFactory;
        this.#component = component;
        this.#queue = queue;
        this.#courseId = courseId;
    }

    /**
     * @param {string} query
     * @return {Element}
     */
    getElement(query) {
        return this.#component.getElement(query);
    }

    /**
     * Wires the dropzone's dragover/dragleave/drop listeners.
     */
    bindDropzone() {
        const dropZone = this.getElement(SELECTORS.CART_DROPZONE);
        this.#component.addEventListener(dropZone, 'dragover', (event) => this.#handleDragOver(event));
        this.#component.addEventListener(dropZone, 'dragleave', (event) => this.#handleDragLeave(event));
        this.#component.addEventListener(dropZone, 'drop', (event) => this.#handleDrop(event));
    }

    /**
     * Delegates settings/delete clicks on cart items instead of binding a
     * listener per rendered item, so items can be added/removed freely
     * without leaking listeners.
     */
    bindItemActions() {
        const cartItems = this.getElement(SELECTORS.CART_ITEMS);
        this.#component.addEventListener(cartItems, 'click', (event) => this.#handleCartItemClick(event));
    }

    /**
     * Wires the clear-cart button, if the template rendered one.
     */
    bindClearCart() {
        const clearCartBtn = this.getElement(SELECTORS.CART_CLEAR_BTN);
        if (clearCartBtn) {
            this.#component.addEventListener(clearCartBtn, 'click', (event) => this.#handleClearCart(event));
        }
    }

    /**
     * Disables the "Copy activities" submit button the moment the cart form is submitted, so a
     * fast double-click can't queue two copy jobs from the same submission - the button stays
     * disabled through the page navigation the submit triggers, so there's nothing to re-enable.
     */
    bindCartFormSubmit() {
        const form = this.getElement(SELECTORS.CART_FORM);
        const submitBtn = this.getElement(SELECTORS.CART_SUBMIT_BTN);
        if (!form || !submitBtn) {
            return;
        }
        this.#component.addEventListener(form, 'submit', () => {
            submitBtn.disabled = true;
        });
    }

    /**
     * Reactive watcher hook body for `cm.dragging:created`/`cm.dragging:updated`.
     * @param {Object} param
     * @param {Object} param.element - The dragged cm state element
     */
    onDraggingCourseModule({element}) {
        this.setDraggedCourseModuleId(element.dragging ? element.id : null);
    }

    /**
     * @param {?number} cmid
     */
    setDraggedCourseModuleId(cmid) {
        window.clearTimeout(this.#clearDragTimeout);

        if (cmid === null) {
            // Slight delay clearing the ID so the drop event has time to read it - cancelled
            // above if a new drag starts before this fires, so it can't null out that newer drag.
            this.#clearDragTimeout = window.setTimeout(() => {
                this.draggedCmId = null;
            }, 100);
        } else {
            this.draggedCmId = cmid;
        }
    }

    /**
     * Adds an activity to the cart - the single method both dragging an
     * activity into the dropzone and clicking the course-page "add to copy
     * cart" button ultimately call, so both are guaranteed to behave identically.
     * @param {number} cmid - The course module ID of the activity
     * @return {Promise}
     */
    addCourseModuleBackupToCopyCart(cmid) {
        return this.#baseFactory.block().item().addActivityToCart(
            this.#component.reactive,
            this.#queue.elements(this.#courseId),
            cmid
        );
    }

    /**
     * @param {DragEvent} event
     */
    #handleDragOver(event) {
        if (!this.draggedCmId) {
            return;
        }
        event.preventDefault();
        event.currentTarget.classList.add('activitycopycart-drag-active');
    }

    /**
     * @param {DragEvent} event
     */
    #handleDragLeave(event) {
        if (!this.draggedCmId) {
            return;
        }
        event.preventDefault();
        event.currentTarget.classList.remove('activitycopycart-drag-active');
    }

    /**
     * @param {DragEvent} event
     */
    #handleDrop(event) {
        if (!this.draggedCmId) {
            return;
        }
        event.preventDefault();
        event.currentTarget.classList.remove('activitycopycart-drag-active');
        this.addCourseModuleBackupToCopyCart(this.draggedCmId);
    }

    /**
     * @param {MouseEvent} event
     */
    #handleClearCart(event) {
        event.preventDefault();
        this.#queue.clearCart(this.#queue.elements(this.#courseId));
    }

    /**
     * @param {MouseEvent} event
     */
    #handleCartItemClick(event) {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) {
            return;
        }
        event.preventDefault();

        const itemEl = actionBtn.closest(SELECTORS.CART_ITEM);
        const cmid = parseInt(itemEl?.dataset.cmid, 10);
        if (!Number.isInteger(cmid)) {
            return;
        }

        if (actionBtn.dataset.action === 'settings') {
            const nameEl = itemEl.querySelector(SELECTORS.CART_ITEM_NAME);
            this.#baseFactory.block().modal().show(
                this.#component.reactive,
                this.#queue.elements(this.#courseId),
                cmid,
                nameEl?.textContent ?? ''
            );
        } else if (actionBtn.dataset.action === 'delete') {
            this.#queue.deleteActivityFromCart(this.#queue.elements(this.#courseId), cmid, itemEl);
        }
    }
}
