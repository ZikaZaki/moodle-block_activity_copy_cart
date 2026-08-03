// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
// eslint-disable-next-line no-unused-vars
import BlockElement from '../element';
import SELECTORS from '../selectors';

// Caps refreshCourseModule()'s retry loop at ~5 seconds - without a cap, a course module whose
// action menu never appears (e.g. deleted before it renders, or an activity type/permission
// combination with no action menu) would retry every 100ms forever, leaking a timer per cmid.
const MAX_REFRESH_ATTEMPTS = 50;

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
     * The in-flight (or resolved) template render, memoized as the promise itself rather than
     * its resolved value - caching the resolved value alone let every concurrent caller during
     * stateReady()'s unawaited forEach() over existing course modules independently trigger its
     * own redundant render before the first one resolved.
     * @type {?Promise<Element>}
     */
    #copyCartButtonPromise;

    /**
     * @param {BaseFactory} baseFactory
     * @param {BlockElement} block
     * @param {boolean} canBackup
     * @param {boolean} showCopyToCartIcon
     */
    constructor(baseFactory, block, canBackup, showCopyToCartIcon) {
        this.#baseFactory = baseFactory;
        this.#block = block;
        this.#canBackup = canBackup;
        this.#showCopyToCartIcon = showCopyToCartIcon;
    }

    /**
     * @return {Promise<Element>} A fresh clone of the (memoized) button element
     */
    async getBackupToCopyCartButton() {
        if (!this.#copyCartButtonPromise) {
            this.#copyCartButtonPromise = this.#baseFactory.moodle().template().createElementFromTemplate(
                'block_activity_copy_cart/block/course/add_to_copy_cart_button',
                {}
            );
        }

        const button = await this.#copyCartButtonPromise;
        return button.cloneNode(true);
    }

    /**
     * Ensures one course module's action menu carries the "add to copy cart"
     * button, retrying until the menu itself exists in the DOM. The button's
     * click is handled by the single delegated listener bindAddToCartButtons()
     * sets up once, not by a listener attached here per instance - this only
     * needs to stamp the activity's own id onto the clone.
     * @param {Object} param
     * @param {Object} param.element - The cm state element
     * @param {number} [attempt] - Retry counter, capped at MAX_REFRESH_ATTEMPTS
     */
    async refreshCourseModule({element}, attempt = 0) {
        if (!this.#showCopyToCartIcon || !this.#canBackup) {
            return;
        }

        const courseModuleActionMenu = document.querySelector(
            `${SELECTORS.COURSE_CONTENT} ${SELECTORS.COURSE_MODULE_ACTION_MENU}[data-cmid="${element.id}"]`
        );
        if (!courseModuleActionMenu) {
            if (attempt >= MAX_REFRESH_ATTEMPTS) {
                return;
            }
            setTimeout(() => this.refreshCourseModule({element}, attempt + 1), 100);
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
