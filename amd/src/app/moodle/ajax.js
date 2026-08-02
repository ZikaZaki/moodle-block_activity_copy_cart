// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import CoreAjax from 'core/ajax';
import {exception as notifyException} from 'core/notification';
import {getString, getStrings} from 'core/str';

export default class Ajax {
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
     * Calls a single external function, returning its own promise directly
     * rather than the array core/ajax's Ajax.call() normally returns.
     * @param {string} methodname
     * @param {Object} args
     * @return {Promise}
     */
    call(methodname, args) {
        return CoreAjax.call([{methodname, args}])[0];
    }

    /**
     * @param {string} key
     * @param {string} component
     * @param {Object|string} [param]
     * @param {string} [lang]
     * @return {Promise<string>}
     */
    getString(key, component, param, lang) {
        return getString(key, component, param, lang);
    }

    /**
     * @param {Array} requests
     * @return {Promise<string[]>}
     */
    getStrings(requests) {
        return getStrings(requests);
    }

    /**
     * Shows a notification for an unhandled promise rejection - bound for direct
     * use as a `.catch((e) => ajax.notifyException(e))` handler.
     * @param {Error} error
     */
    notifyException(error) {
        return notifyException(error);
    }
}
