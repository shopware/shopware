import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * this plugin is used to remotely click on another element
 */
export default class RemoteClickPlugin extends Plugin {

    static options = {

        /**
         * selector for which element should be clicked
         */
        selector: false,

        /**
         * if the window should be scrolled to the remotely clicked element
         */
        scrollToElement: true,

        /**
         * how much px the scrolling should be offset
         */
        scrollOffset: 15,

        /**
         * selector for the fixed header element
         */
        fixedHeaderSelector: 'header.fixed-top',
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
     * @private
     */
    _onClick() {
        let target = this.options.selector;
        if (!DomAccess.isNode(this.options.selector)) {
            target = DomAccess.querySelector(document, this.options.selector);
        }

        if (this.options.scrollToElement) {
            this._scrollToElement(target);
        }

        let targetEvent = null;
        if (document.createEvent) {
            targetEvent = document.createEvent('MouseEvents');
            targetEvent.initEvent('click', true, true);
        } else {
            targetEvent = new MouseEvent('click', {target});
        }

        target.dispatchEvent(targetEvent);

        this.$emitter.publish('onClick');
    }

    /**
     * scrolls to the provided element
     *
     * @param  {HTMLElement} target
     * @private
     */
    _scrollToElement(target) {
        const top = this._getOffset(target);
        window.scrollTo({
            top,
            behavior: 'smooth',
        });
    }

    /**
     * returns the calculated offset to scroll to
     *
     * @param  {HTMLElement} target
     * @returns {number}
     * @private
     */
    _getOffset(target) {
        const rect = target.getBoundingClientRect();
        const elementScrollOffset = rect.top + window.scrollY;
        let offset = elementScrollOffset - this.options.scrollOffset;

        const fixedHeader = DomAccess.querySelector(document, this.options.fixedHeaderSelector, false);
        if (fixedHeader) {
            const headerRect = fixedHeader.getBoundingClientRect();
            offset -= headerRect.height;
        }

        return offset;
    }

}
