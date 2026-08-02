import BlockFactory from "./block/factory";
import MoodleFactory from "./moodle/factory";

export default class BaseFactory {
    /**
     * @type {Object|null} The reactive Block instance (`this` from local/block.js),
     * or null on pages with no reactive component at all (target_courses.php, copy_progress.php).
     */
    #component;

    /**
     * @param {Object|null} component
     */
    constructor(component = null) {
        this.#component = component;
    }

    /**
     * @param {Object|null} component - The reactive Block instance, or null outside its lifecycle
     * @returns {BaseFactory}
     */
    static make(component = null) {
        return new this(component);
    }

    /**
     * @returns {Object|null}
     */
    component() {
        return this.#component;
    }

    /**
     * @returns {BlockFactory}
     */
    block() {
        return new BlockFactory(this);
    }

    /**
     * @returns {MoodleFactory}
     */
    moodle() {
        return new MoodleFactory(this);
    }
}
