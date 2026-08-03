// eslint-disable-next-line no-unused-vars
import BaseFactory from '../factory';
import Fragment from "core/fragment";
import Templates from "core/templates";

export default class Template {
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
     * @param {String} template
     * @param {Object} data
     * @return {Promise<
     * {
     *  html: String,
     *  js: String
     * }
     * >}
     */
    async renderTemplate(template, data) {
        return await new Promise((resolve, reject) => {
            Templates.render(template, data)
                .then((html, js) => {
                    return resolve({
                        html,
                        js
                    });
                }).fail(reject);
        });
    }

    /**
     * @param {String} component
     * @param {String} fragment
     * @param {Number} contextId
     * @param {Object} data
     * @return {Promise<
     * {
     *  html: String,
     *  js: String
     * }
     * >}
     */
    async renderFragment(component, fragment, contextId, data) {
        return await new Promise((resolve, reject) => {
            Fragment.loadFragment(
                component,
                fragment,
                contextId,
                data
            ).then((html, js) => {
                return resolve({
                    html,
                    js
                });
            }).fail(reject);
        });
    }

    /**
     * @param {String} js
     */
    runTemplateJS(js) {
        Templates.runTemplateJS(js);
    }

    /**
     * Appends rendered HTML/JS to the end of a container's contents.
     * @param {Element} element
     * @param {String} html
     * @param {String} js
     */
    appendNodeContents(element, html, js) {
        Templates.appendNodeContents(element, html, js);
    }

    /**
     * Replaces a container's contents with rendered HTML/JS.
     * @param {Element} element
     * @param {String} html
     * @param {String} js
     */
    replaceNodeContents(element, html, js) {
        Templates.replaceNodeContents(element, html, js);
    }

    /**
     * @param {String} template
     * @param {Object} data
     * @return {Promise<HTMLElement>}
     */
    async createElementFromTemplate(template, data) {
        const element = document.createElement('div');

        const {html, js} = await this.renderTemplate(template, data);

        // Templates.replaceNode() returns its array of nodes synchronously, not a promise -
        // no await here (an earlier one was misleading, implying otherwise).
        return Templates.replaceNode(
            element,
            html,
            js
        )[0];
    }


    /**
     * @param {String} component
     * @param {String} fragment
     * @param {Number} contextId
     * @param {Object} data
     * @return {Promise<HTMLElement[]>}
     */
    async createElementsFromFragment(component, fragment, contextId, data) {
        const element = document.createElement('div');

        const {html, js} = await this.renderFragment(component, fragment, contextId, data);

        // Templates.replaceNode() returns its array of nodes synchronously, not a promise -
        // no await here (an earlier one was misleading, implying otherwise).
        return Templates.replaceNode(
            element,
            html,
            js
        );
    }

    /**
     * @param {String} component
     * @param {String} fragment
     * @param {Number} contextId
     * @param {Object} data
     * @return {Promise<HTMLElement>}
     */
    async createElementFromFragment(component, fragment, contextId, data) {
        return (await this.createElementsFromFragment(component, fragment, contextId, data))[0];
    }
}