// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import EventHandler from "./event_handler";
import Item from './item/element';
import Modal from './item/modal';
import Queue from './queue/factory';
import Autosave from './queue/autosave';

export default class Factory {
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
     * @returns {EventHandler}
     */
    eventHandler() {
        return new EventHandler(this.#baseFactory);
    }

    /**
     * @returns {Item}
     */
    item() {
        return new Item(this.#baseFactory);
    }

    /**
     * @returns {Modal}
     */
    modal() {
        return new Modal(this.#baseFactory);
    }

    /**
     * Owns the page's one SortableList instance - see
     * app/block/event_handler.js's onLoad() for the singleton-construction rule.
     * @returns {Queue}
     */
    queue() {
        return new Queue(this.#baseFactory);
    }

    /**
     * @returns {Autosave}
     */
    autosave() {
        return new Autosave(this.#baseFactory);
    }
}
