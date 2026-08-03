// eslint-disable-next-line no-unused-vars
import BaseFactory from '../../factory';
import SELECTORS from '../selectors';
import Settings from '../item/settings';
import {getSavedValue, debounce} from '../../../lib/helpers';

const AUTOSAVE_DEBOUNCE_MS = 500;

/**
 * @param {BaseFactory} baseFactory
 * @param {HTMLFormElement} form
 * @param {number} courseId
 * @return {Promise}
 */
async function save(baseFactory, form, courseId) {
    const cmids = Array.from(form.querySelectorAll(SELECTORS.CART_CMIDS_INPUT))
        .map((input) => parseInt(input.value, 10));

    const items = cmids.map((cmid) => ({
        cmid,
        rename: getSavedValue(`rename-hidden-${cmid}`, ''),
        sectionmatch: getSavedValue(`sectionmatch-hidden-${cmid}`, Settings.SECTION_MATCH.NAME),
        section: parseInt(getSavedValue(`section-hidden-${cmid}`, '0'), 10) || 0,
        sectionname: getSavedValue(`sectionname-hidden-${cmid}`, ''),
        sectionmissing: getSavedValue(`sectionmissing-hidden-${cmid}`, Settings.SECTION_MISSING.CREATE),
        nameconflict: getSavedValue(`nameconflict-hidden-${cmid}`, Settings.NAME_CONFLICT.RESOLVE),
        visibility: getSavedValue(`visibility-hidden-${cmid}`, Settings.VISIBILITY.SOURCE),
        restrictions: getSavedValue(`restrictions-hidden-${cmid}`, '1') === '1',
    }));

    const ajax = baseFactory.moodle().ajax();
    try {
        return await ajax.call('block_activity_copy_cart_save_cart', {courseid: courseId, cmids, items});
    } catch (error) {
        return ajax.notifyException(error);
    }
}

/**
 * Schedules (debounced) an autosave of the cart form's current state - there's
 * only ever one cart on the page, so every call site sharing this one
 * module-level debounced function is exactly what's wanted (a burst of
 * adds/deletes autosaves once, not once per change). It stays module-level
 * (not instance state) deliberately: Autosave instances are constructed
 * fresh on every `baseFactory.block().autosave()` call, matching Item/Modal's
 * own "cheap, resolve fresh" pattern, and only a module-level singleton
 * guarantees the debounce is actually shared across all of them.
 * @type {Function}
 */
const scheduledSave = debounce(save, AUTOSAVE_DEBOUNCE_MS);

export default class Autosave {
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
     * @param {HTMLFormElement} form - The cart form (#activitycopycart-form)
     * @param {number} courseId - The course this block instance lives on
     */
    schedule(form, courseId) {
        scheduledSave(this.#baseFactory, form, courseId);
    }
}
