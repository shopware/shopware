import Plugin from 'src/script/plugin-system/plugin.class';
import DomAccess from 'src/script/helper/dom-access.helper';

/**
 * this plugin is used to remotely click on another element
 */
export default class RemoteClickPlugin extends Plugin {

    static options = {
        selector: false,
    };

    init() {
        if (!this.options.selector) {
            throw new Error('The option "selector" must be given!');
        }
        this._registerEvents();
    }

    /**
     * register needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('click', this._onClick.bind(this));
    }

    /**
     * click event handler
     *
     * @param {Event} event
     *
     * @private
     */
    _onClick(event) {
        event.preventDefault();
        let target = this.options.selector;
        if (!DomAccess.isNode(this.options.selector)) {
            target = DomAccess.querySelector(document, this.options.selector);
        }

        const passEvent = new MouseEvent('click', { target });
        target.dispatchEvent(passEvent);

        this.$emitter.publish('onClick');
    }
}
