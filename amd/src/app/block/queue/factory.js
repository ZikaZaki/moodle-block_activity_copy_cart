// eslint-disable-next-line no-unused-vars
import BaseFactory from '../../factory';
import $ from 'jquery';
import SortableList from 'core/sortable_list';
import SELECTORS from '../selectors';
import {addArrayInput} from '../../../lib/helpers';

export default class Queue {
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
     * Bundles the DOM refs the queue (and item) modules need. Resolved fresh on
     * every call rather than cached, since it's cheap and avoids the caller
     * having to track whether cached refs are still attached to the page.
     * @param {number} courseId - The course this block instance lives on
     * @return {Object}
     */
    elements(courseId) {
        const component = this.#baseFactory.component();
        return {
            form: component.getElement(SELECTORS.CART_FORM),
            cartItems: component.getElement(SELECTORS.CART_ITEMS),
            emptyMsg: component.getElement(SELECTORS.CART_EMPTY_MSG),
            clearCartBtn: component.getElement(SELECTORS.CART_CLEAR_BTN),
            submitBtn: component.getElement(SELECTORS.CART_SUBMIT_BTN),
            courseId,
        };
    }

    /**
     * Keeps the empty-message/clear-button/submit-button in sync with whether
     * the cart currently holds any activities. Called after every mutation
     * below so callers can't forget to refresh these controls - also called on
     * its own by app/block/event_handler.js at startup, since a restored cart
     * (classes/app/cart/manager.php) needs the same sync without having gone
     * through any of the mutation methods below.
     * @param {Object} elements
     * @param {HTMLFormElement} elements.form
     * @param {HTMLElement} [elements.emptyMsg]
     * @param {HTMLElement} [elements.clearCartBtn]
     * @param {HTMLElement} [elements.submitBtn]
     */
    updateCartControls({form, emptyMsg, clearCartBtn, submitBtn}) {
        const hasItems = form.querySelectorAll(SELECTORS.CART_CMIDS_INPUT).length > 0;

        if (emptyMsg) {
            emptyMsg.style.display = hasItems ? 'none' : 'block';
        }
        if (clearCartBtn) {
            clearCartBtn.disabled = !hasItems;
            clearCartBtn.style.display = hasItems ? 'inline-block' : 'none';
        }
        if (submitBtn) {
            submitBtn.disabled = !hasItems;
        }
    }

    /**
     * Deletes an individual activity from the cart, including any settings
     * previously saved for it, so re-adding the same activity later starts fresh.
     * @param {Object} elements - {form, emptyMsg, clearCartBtn, submitBtn, courseId}
     * @param {number} cmid - The course module ID of the activity to remove
     * @param {HTMLElement} itemElement - The DOM element representing the cart item
     */
    deleteActivityFromCart(elements, cmid, itemElement) {
        itemElement.remove();

        const {form} = elements;
        form.querySelectorAll(`${SELECTORS.CART_CMIDS_INPUT}[value="${cmid}"]`).forEach((input) => input.remove());
        form.querySelectorAll(`[id$="-hidden-${cmid}"]`).forEach((input) => input.remove());

        this.updateCartControls(elements);
        this.#baseFactory.block().autosave().schedule(form, elements.courseId);
    }

    /**
     * Resets the entire cart, including any per-item settings saved so far.
     * @param {Object} elements - {form, cartItems, emptyMsg, clearCartBtn, submitBtn, courseId}
     */
    clearCart(elements) {
        const {form, cartItems} = elements;

        cartItems.querySelectorAll(SELECTORS.CART_ITEM).forEach((item) => item.remove());
        form.querySelectorAll(SELECTORS.CART_CMIDS_INPUT).forEach((input) => input.remove());
        form.querySelectorAll('input[id*="-hidden-"]').forEach((input) => input.remove());

        this.updateCartControls(elements);
        this.#baseFactory.block().autosave().schedule(form, elements.courseId);
    }

    /**
     * Rebuilds the form's cmids[] hidden inputs to match the cart items' current
     * DOM order, after core/sortable_list has reordered them - cmids[] is what
     * the form actually submits from, and PARAM_INT arrays preserve submission
     * order end-to-end (cart\manager::build() iterates them in the order
     * given), so this is the one thing that has to stay in sync with the
     * visual order for a reorder to actually mean anything downstream.
     * @param {Object} elements - {form, cartItems, courseId}
     */
    reorderCartItems(elements) {
        const {form, cartItems} = elements;

        const orderedCmids = Array.from(cartItems.querySelectorAll(SELECTORS.CART_ITEM))
            .map((item) => parseInt(item.dataset.cmid, 10));

        form.querySelectorAll(SELECTORS.CART_CMIDS_INPUT).forEach((input) => input.remove());
        orderedCmids.forEach((cmid) => addArrayInput(form, 'cmids[]', cmid));

        this.#baseFactory.block().autosave().schedule(form, elements.courseId);
    }

    /**
     * Wires up drag-to-reorder on the cart items list via Moodle's own
     * core/sortable_list (mouse, touch, and a keyboard-accessible "move to..."
     * dialog via each item's core/drag_handle - see templates/cart/item.mustache)
     * rather than a 3rd-party library, since core already provides this and
     * this plugin would otherwise be shipping a redundant dependency.
     * @param {HTMLElement} cartItems
     * @param {Function} getElements - Returns a fresh {form, cartItems, courseId} bundle - called on
     *   every drop rather than once at setup, matching elements()'s own "resolve fresh" contract
     * @return {SortableList}
     */
    initSortable(cartItems, getElements) {
        const sortableList = new SortableList(cartItems);
        $(cartItems).on(SortableList.EVENTS.DROP, (event, info) => {
            if (info.positionChanged) {
                this.reorderCartItems(getElements());
            }
        });
        return sortableList;
    }
}
