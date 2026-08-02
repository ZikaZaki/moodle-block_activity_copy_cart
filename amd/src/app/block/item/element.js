// eslint-disable-next-line no-unused-vars
import BaseFactory from '../../factory';
import SELECTORS from '../selectors';
import {addArrayInput} from '../../../lib/helpers';

/**
 * Reads the activity's own icon URL straight off its course-page markup
 * (core_courseformat renders every activity with an
 * `<img class="activityicon" data-region="activity-icon" data-id="{cmid}">`
 * @param {number} cmid - The course module ID of the dropped activity
 * @return {string} The icon URL, or an empty string if it couldn't be found
 */
function getActivityIconUrl(cmid) {
    const icon = document.querySelector(`[data-region="activity-icon"][data-id="${cmid}"]`);
    return icon?.src ?? '';
}

export default class Item {
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
     * Appends an activity to the cart UI, rendered from the
     * block_activity_copy_cart/block/item mustache template. Shared by both the
     * dropzone's drop handler and the course-page "add to copy cart" button.
     * @param {Object} reactive - The course editor reactive state manager
     * @param {Object} elements - {form, cartItems, emptyMsg, clearCartBtn, submitBtn, courseId}
     * @param {number} cmid - The course module ID of the activity
     * @return {Promise}
     */
    async addActivityToCart(reactive, elements, cmid) {
        cmid = parseInt(cmid, 10);
        if (!Number.isInteger(cmid)) {
            return null;
        }

        const {form, cartItems} = elements;

        // Prevent duplicate additions.
        if (form.querySelector(`${SELECTORS.CART_CMIDS_INPUT}[value="${cmid}"]`)) {
            return null;
        }

        // Fetch the activity name directly from Moodle's reactive state.
        const cmState = reactive.state.cm.get(cmid);
        const cmName = cmState?.name ?? '';
        const iconUrl = getActivityIconUrl(cmid);

        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        try {
            const movetitle = await ajax.getString('movecontent', 'moodle', cmName);
            const {html, js} = await template.renderTemplate(
                'block_activity_copy_cart/block/item',
                {cmid, cmname: cmName, iconurl: iconUrl, movetitle}
            );

            template.appendNodeContents(cartItems, html, js);
            addArrayInput(form, 'cmids[]', cmid);
            this.#baseFactory.block().queue().updateCartControls(elements);
            this.#baseFactory.block().autosave().schedule(form, elements.courseId);
            return null;
        } catch (error) {
            ajax.notifyException(error);
            return null;
        }
    }
}
