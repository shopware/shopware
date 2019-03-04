import Emitter from './emitter.helper';

export default class Plugin extends Emitter {
    constructor(name) {
        super();

        this.name = name;
    }

    /**
     * Merges the user configuration with the defaults of the plugin.
     *
     * @param {Object} userConfig
     * @param {Object} [defaults = {}]
     * @returns {Object}
     */
    getConfig(userConfig, defaults = {}) {
        return Object.assign({}, defaults, userConfig);
    }

    /**
     * Creates a virtual element which can be rendered later on using `render` method.
     *
     * @param {String} tagName
     * @param {Object} attrs
     * @param {Array} children
     * @param {Object} listeners
     * @returns {Object}
     */
    h(tagName, { attrs = {}, children = [], listeners = {}, html = '', text = '' }) {
        const node = Object.create(null);

        Object.assign(node, {
            tagName,
            attrs,
            children,
            listeners,
            html,
            text
        });

        return node;
    }

    render(node) {
        // Create element
        if (typeof node === 'string') {
            return document.createTextNode(node);
        }
        const $el = document.createElement(node.tagName);

        // Add attributes
        Object.entries(node.attrs).forEach((values) => {
            const [key, value] = values;
            $el.setAttribute(key, value);
        });

        // Add event listeners
        Object.entries(node.listeners).forEach((values) => {
            const [key, value] = values;
            $el.addEventListener(key, value, false);
        });

        // Add child elements
        if (node.children && node.children.length > 0) {
            node.children.forEach((child) => {
                $el.appendChild(this.render(child));
            });
        }

        // Add text to element
        if (node.text && node.text.length > 0) {
            $el.appendChild(document.createTextNode(node.text));
        }

        // Set html content into an element
        if (node.html && node.html.length > 0) {
            $el.innerHTML = node.html;
        }

        return $el;
    }
}
