import Settings from './settings';

/**
 * Reads a required field's current value via its own SETTINGS_FIELDS
 * entry - reusing the exact selector settings.js already defines (and
 * modal.js's saveItemSettings() already reads from) for that field, rather
 * than this re-deriving the same selector independently, which is what would
 * otherwise leave the two silently able to drift apart.
 * @param {string} key - One of the keys in Settings.SETTINGS_FIELDS
 * @param {Element} root - The modal's root element
 * @param {number} cmid - The course module ID
 * @returns {*}
 */
function readField(key, root, cmid) {
    return Settings.SETTINGS_FIELDS.find((field) => field.key === key).read(root, cmid);
}

/**
 * Validates the modal's required Settings for one activity.
 * @param {number} cmid - The course module ID
 * @param {Modal} modal - The settings modal instance being validated
 * @param {Object} strings - Resolved error message per field key (see modal.js)
 * @returns {Object<string, string>} Error message per field key that failed, empty if valid
 */
export function validate(cmid, modal, strings) {

    const root = modal.getRoot()[0];
    const errors = {};

    if (!readField('section', root, cmid)) {
        errors.section = strings.section;
    }

    if (!readField('sectionmatch', root, cmid)) {
        errors.sectionmatch = strings.sectionmatch;
    }

    if (!readField('sectionmissing', root, cmid)) {
        errors.sectionmissing = strings.sectionmissing;
    }

    if (!readField('nameconflict', root, cmid)) {
        errors.nameconflict = strings.nameconflict;
    }

    if (!readField('visibility', root, cmid)) {
        errors.visibility = strings.visibility;
    }

    return errors;
}

/**
 * Removes every validation error previously shown in the modal.
 * @param {Element} root - The modal's root element
 */
export function clearErrors(root) {
    root.querySelectorAll('.activitycopycart-field-error').forEach(el => el.remove());
    root.querySelectorAll('.activitycopycart-has-error').forEach(el => el.classList.remove('activitycopycart-has-error'));
}

/**
 * Finds the field element a validation error belongs to. Fields looked up
 * by id (section, visibility) read their id prefix from SETTINGS_FIELDS
 * itself, rather than re-declaring it here, so this can't silently drift
 * out of sync with settings.js's own read() selectors for the same field.
 * Every other field is a name="{key}-{cmid}" radio group, keyed generically.
 * @param {Element} root - The modal's root element
 * @param {string} key - One of the keys in Settings.SETTINGS_FIELDS
 * @return {?Element}
 */
function findField(root, key) {
    const idPrefix = Settings.SETTINGS_FIELDS.find((field) => field.key === key)?.idPrefix;
    if (idPrefix) {
        return root.querySelector(`[id^="${idPrefix}"]`);
    }
    return root.querySelector(`[name^="${key}-"]`);
}

/**
 * Shows each field-level validation error next to its field.
 * @param {Element} root - The modal's root element
 * @param {Object<string, string>} errors - Error message per field key
 */
export function showErrors(root, errors) {

    Object.entries(errors).forEach(([key, message]) => {

        const field = findField(root, key);

        if (!field) {
            return;
        }

        const container = field.closest('.form-group, div');
        container.classList.add('activitycopycart-has-error');

        const error = document.createElement('div');
        error.className = 'activitycopycart-field-error';
        error.textContent = message;
        container.appendChild(error);
    });
}

/**
 * Shows (or updates) a summary banner listing every validation error.
 * @param {Element} root - The modal's root element
 * @param {Object<string, string>} errors - Error message per field key
 * @param {string} heading - Localized heading text for the summary banner
 */
export function showSummary(root, errors, heading) {

    let summary = root.querySelector('.activitycopycart-error-summary');

    if (!summary) {
        summary = document.createElement('div');
        summary.className = 'activitycopycart-error-summary';
        root.prepend(summary);
    }

    summary.replaceChildren();

    const strong = document.createElement('strong');
    strong.textContent = heading;
    summary.appendChild(strong);

    const list = document.createElement('ul');
    Object.values(errors).forEach(message => {
        const item = document.createElement('li');
        item.textContent = message;
        list.appendChild(item);
    });
    summary.appendChild(list);
}
