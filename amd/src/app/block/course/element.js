// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
// eslint-disable-next-line no-unused-vars
import BlockElement from '../element';
import SELECTORS from '../selectors';

export default class CourseElement {
    /**
     * @type {BaseFactory}
     */
    #baseFactory;

    /**
     * @type {BlockElement}
     */
    #block;

    /**
     * @type {boolean}
     */
    #canBackup;

    /**
     * @type {boolean}
     */
    #showCopyToCartIcon;

    /**
     * Capability data plumbed through for a future feature (gating the
     * "Include user data"/anonymize options) - not yet consumed anywhere.
     * @type {boolean}
     */
    #canBackupUserdata;

    /**
     * @type {boolean}
     */
    #canAnonymizeUserdata;

    /**
     * @type {?Element}
     */
    #copyCartButton;

    /**
     * @param {BaseFactory} baseFactory
     * @param {BlockElement} block
     * @param {boolean} canBackup
     * @param {boolean} showCopyToCartIcon
     * @param {boolean} canBackupUserdata
     * @param {boolean} canAnonymizeUserdata
     */
    constructor(baseFactory, block, canBackup, showCopyToCartIcon, canBackupUserdata, canAnonymizeUserdata) {
        this.#baseFactory = baseFactory;
        this.#block = block;
        this.#canBackup = canBackup;
        this.#showCopyToCartIcon = showCopyToCartIcon;
        this.#canBackupUserdata = canBackupUserdata;
        this.#canAnonymizeUserdata = canAnonymizeUserdata;
    }

    /**
     * @return {Promise<Element>} A fresh clone of the (memoized) button element
     */
    async getBackupToCopyCartButton() {
        if (!this.#copyCartButton) {
            this.#copyCartButton = await this.#baseFactory.moodle().template().createElementFromTemplate(
                'block_activity_copy_cart/block/course/add_to_copy_cart_button',
                {}
            );
        }

        return this.#copyCartButton.cloneNode(true);
    }

    /**
     * Ensures one course module's action menu carries the "add to copy cart"
     * button, retrying until the menu itself exists in the DOM. The button's
     * click is handled by the single delegated listener bindAddToCartButtons()
     * sets up once, not by a listener attached here per instance - this only
     * needs to stamp the activity's own id onto the clone.
     * @param {Object} param
     * @param {Object} param.element - The cm state element
     */
    async refreshCourseModule({element}) {
        if (!this.#showCopyToCartIcon || !this.#canBackup) {
            return;
        }

        const courseModuleActionMenu = document.querySelector(
            `${SELECTORS.COURSE_CONTENT} ${SELECTORS.COURSE_MODULE_ACTION_MENU}[data-cmid="${element.id}"]`
        );
        if (!courseModuleActionMenu) {
            setTimeout(() => this.refreshCourseModule({element}), 100);
            return;
        }

        if (!courseModuleActionMenu.querySelector(SELECTORS.ADD_TO_COPY_CART_BUTTON)) {
            const backupButton = await this.getBackupToCopyCartButton();
            backupButton.dataset.cmid = element.id;
            courseModuleActionMenu.append(backupButton);
        }
    }

    /**
     * Wires one delegated click listener for every "add to copy cart" button
     * on the course page, present or future - cheaper and simpler than
     * binding a listener per button in refreshCourseModule(), and matches
     * app/block/element.js's #handleCartItemClick, which delegates the same
     * way for cart item actions.
     */
    bindAddToCartButtons() {
        const courseContent = document.querySelector(SELECTORS.COURSE_CONTENT);
        if (!courseContent) {
            return;
        }

        courseContent.addEventListener('click', (event) => {
            const button = event.target.closest(SELECTORS.ADD_TO_COPY_CART_BUTTON);
            if (!button) {
                return;
            }

            event.preventDefault();
            const cmid = parseInt(button.dataset.cmid, 10);
            if (Number.isInteger(cmid)) {
                this.#block.addCourseModuleBackupToCopyCart(cmid);
            }
        });
    }
}
