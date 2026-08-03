// eslint-disable-next-line no-unused-vars
import BaseFactory from '../../factory';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import SELECTORS from '../selectors';
import Settings from './settings';
import {validate, clearErrors, showErrors, showSummary} from './validation';
import {getSavedValue, setHiddenInput} from '../../../lib/helpers';

// Each request carries its own validationStrings field name, so reordering or adding an
// entry can't silently desync a request from the wrong resolved string - see how this is
// consumed in show() below, which pairs field/string by walking this same array, not by
// positional destructuring of a separately-maintained array of resolved values.
const VALIDATION_STRING_REQUESTS = [
    {field: 'section', key: 'error_sectionrequired', component: 'block_activity_copy_cart'},
    {field: 'sectionmatch', key: 'error_sectionmatchrequired', component: 'block_activity_copy_cart'},
    {field: 'sectionmissing', key: 'error_sectionmissingrequired', component: 'block_activity_copy_cart'},
    {field: 'nameconflict', key: 'error_nameconflictrequired', component: 'block_activity_copy_cart'},
    {field: 'visibility', key: 'error_visibilityrequired', component: 'block_activity_copy_cart'},
];

/**
 * Build an object of boolean flags for each enum value,
 * indicating which one is currently selected.
 * @param {string} prefix
 * @param {string} current
 * @param {object} enumValues
 * @returns {object}
 */
function enumFlags(prefix, current, enumValues) {

    const flags = {};

    Object.values(enumValues).forEach(value => {
        flags[`${prefix}${value}`] = current === value;
    });

    return flags;
}

/**
 * Builds the list of source-course sections for the target section dropdown,
 * marking whichever one matches the currently selected section number.
 * @param {Object} reactive - The course editor reactive state manager
 * @param {number} currentSectionNum - The section number that should appear pre-selected
 * @returns {Array} List of {number, title, selected} objects for the mustache template
 */
function getSectionsContext(reactive, currentSectionNum) {

    const sectionList = reactive.state.course.sectionlist ?? [];

    return sectionList.map(sectionId => {

        const sectionState = reactive.state.section.get(sectionId);

        return {
            number: sectionState.section,
            title: sectionState.title,
            selected: sectionState.section === currentSectionNum
        };
    });
}

/**
 * Builds the context object for the settings modal mustache template,
 * including saved values and flags for selected options.
 * @param {Object} reactive - The course editor reactive state manager
 * @param {number} cmid - The course module ID
 * @param {string} cmName - The name of the course module
 * @param {Object} saved - The saved settings for the course module
 * @returns {Object} Context object for the mustache template
 */
function buildContext(reactive, cmid, cmName, saved) {

    const cmState = reactive.state.cm.get(cmid);
    const currentSectionNum =
        parseInt(saved.section || cmState?.sectionnumber, 10);

    return Object.assign(
        {
            cmid,
            cmName,
            keeprestrictions: saved.restrictions === '1',
            sections: getSectionsContext(reactive, currentSectionNum),
        },
        enumFlags('sectionmatch', saved.sectionmatch, Settings.SECTION_MATCH),
        enumFlags('sectionmissing', saved.sectionmissing, Settings.SECTION_MISSING),
        enumFlags('nameconflict', saved.nameconflict, Settings.NAME_CONFLICT),
        enumFlags('visibility', saved.visibility, Settings.VISIBILITY)
    );
}

/**
 * Retrieves the saved settings for a given course module ID (cmid) from
 * the cart form's own hidden inputs.
 * @param {number} cmid - The course module ID
 * @param {HTMLFormElement} form - The cart form the saved hidden inputs live on
 * @returns {Object} An object containing the saved settings for the activity
 */
function getSavedSettings(cmid, form) {

    const saved = {};

    Settings.SETTINGS_FIELDS.forEach(({key, defaultValue}) => {
        saved[key] = getSavedValue(form, `${key}-hidden-${cmid}`, defaultValue);
    });

    return saved;
}

export default class Modal {
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
     * Displays the per-activity item settings modal, pre-populated with saved values,
     * and sets up the save event to persist the values back to hidden form inputs.
     * @param {Object} reactive - The course editor reactive state manager
     * @param {Object} elements - {form, cartItems, emptyMsg, clearCartBtn, submitBtn, courseId}
     * @param {number} cmid - The course module ID of the activity
     * @param {string} cmName - The name of the activity
     * @returns {Promise} Resolves when the modal is shown and event handlers are set up
     */
    async show(reactive, elements, cmid, cmName) {
        const currentRename = getSavedValue(elements.form, `rename-hidden-${cmid}`, cmName);
        const saved = getSavedSettings(cmid, elements.form);
        const templateContext = buildContext(reactive, cmid, currentRename, saved);

        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        try {
            const [title, resolvedValidationStrings, summaryHeading] = await Promise.all([
                ajax.getString('settings', 'block_activity_copy_cart'),
                ajax.getStrings(VALIDATION_STRING_REQUESTS),
                ajax.getString('error_summaryheading', 'block_activity_copy_cart'),
            ]);

            const validationStrings = {};
            VALIDATION_STRING_REQUESTS.forEach(({field}, index) => {
                validationStrings[field] = resolvedValidationStrings[index];
            });

            const modal = await ModalSaveCancel.create({
                title,
                show: true,
                removeOnClose: true
            });

            // currentRename (not cmName) so reopening settings for an already-renamed activity
            // shows its current name, not the pre-rename original. Appended as a text node
            // rather than concatenated into the title string, since it's untrusted (an activity
            // name) and Modal#setTitle() inserts that string as raw HTML.
            modal.getTitle()[0]?.appendChild(document.createTextNode(`: ${currentRename}`));

            modal.setBodyContent(
                template.renderTemplate('block_activity_copy_cart/block/item/settings/modal', templateContext)
            );

            modal.getRoot().on(
                ModalEvents.save,
                (e) => {
                    const errors = validate(cmid, modal, validationStrings);
                    const root = modal.getRoot()[0];

                    clearErrors(root);

                    if (Object.keys(errors).length > 0) {
                        e.preventDefault();
                        showSummary(root, errors, summaryHeading);
                        showErrors(root, errors);
                        return;
                    }
                    this.#saveItemSettings(elements, cmid, modal, cmName);
                }
            );

            return modal;
        } catch (error) {
            ajax.notifyException(error);
            return null;
        }
    }

    /**
     * Saves the settings for a given course module ID (cmid) as hidden
     * inputs on the cart form, and reflects a rename back onto the cart
     * item's visible label.
     * @param {Object} elements - {form, cartItems, emptyMsg, clearCartBtn, submitBtn, courseId}
     * @param {number} cmid - The course module ID
     * @param {Object} modal - The modal instance
     * @param {string} originalName - The activity's own name, to restore the visible label to if the rename is cleared
     */
    #saveItemSettings(elements, cmid, modal, originalName) {

        const root = modal.getRoot()[0];
        const {form, cartItems} = elements;

        // Rename - always written (even when cleared back to ''), so clearing the field
        // actually reverts the cart item's label instead of silently keeping a stale rename.
        const renameVal = root.querySelector(`#rename-input-${cmid}`)?.value ?? '';
        setHiddenInput(form, `rename[${cmid}]`, renameVal, `rename-hidden-${cmid}`);

        const nameEl = cartItems.querySelector(
            `${SELECTORS.CART_ITEM}[data-cmid="${cmid}"] ${SELECTORS.CART_ITEM_NAME}`
        );

        if (nameEl) {
            nameEl.textContent = renameVal !== '' ? renameVal : originalName;
        }

        Settings.SETTINGS_FIELDS.forEach(({key, defaultValue, read}) => {
            const value = read(root, cmid) ?? defaultValue;
            setHiddenInput(form, `${key}[${cmid}]`, value, `${key}-hidden-${cmid}`);
        });

        this.#baseFactory.block().autosave().schedule(form, elements.courseId);
    }
}
