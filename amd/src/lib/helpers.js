/**
 * Reads a previously saved hidden input value, falling back to a default
 * when the item has no saved settings yet.
 * @param {string} id - The DOM ID of the hidden input
 * @param {string} fallback - The value to use when nothing was saved yet
 * @return {string}
 */
export const getSavedValue = (id, fallback) => {
    const value = document.getElementById(id)?.value;
    return (value === undefined || value === '') ? fallback : value;
};

/**
 * Creates or updates a keyed hidden input for modal settings data.
 * @param {HTMLFormElement} form - The form the input belongs to
 * @param {string} name - The form input name attribute
 * @param {string} value - The form input value
 * @param {string} id - The DOM ID for the input
 */
export const setHiddenInput = (form, name, value, id) => {
    let input = form.querySelector(`#${id}`);

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.id = id;
        input.name = name;
        form.appendChild(input);
    }
    input.value = value;
};

/**
 * Appends a new hidden input for an array-style form field (e.g. cmids[]).
 * Unlike keyed settings inputs, array inputs have no id and are never
 * updated in place - a new one is added per item.
 * @param {HTMLFormElement} form - The form the input belongs to
 * @param {string} name - The form input name attribute
 * @param {string|number} value - The form input value
 */
export const addArrayInput = (form, name, value) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
};

/**
 * Wraps a function so repeated calls within `ms` of each other collapse
 * into a single call, `ms` after the last one - e.g. a burst of checkbox
 * toggles or cart edits autosaves once, not once per change.
 * @param {Function} fn - The function to debounce, called with the latest arguments it was invoked with
 * @param {number} ms - How long to wait after the last call before actually invoking fn
 * @return {Function} A debounced wrapper around fn, with the same call signature
 */
export const debounce = (fn, ms) => {
    let handle = null;
    return (...args) => {
        window.clearTimeout(handle);
        handle = window.setTimeout(() => fn(...args), ms);
    };
};