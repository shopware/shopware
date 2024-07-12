import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';
import Iterator from 'src/helper/iterator.helper';

export default class NavbarPlugin extends Plugin {
    static options = {
        /**
         * Hover debounce delay.
         */
        debounceTime: 125,
        /**
         * Class to select the top level links.
         */
        topLevelLinksSelector: '.main-navigation-link',
    };

    init() {
        this._topLevelLinks = this.el.querySelectorAll(`${this.options.topLevelLinksSelector}`);
        this._registerEvents();
        this._isMouseOver = false;
    }

    _registerEvents() {
        const openEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'mouseenter';
        const closeEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'mouseleave';
        const clickEvent = (DeviceDetection.isTouchDevice()) ? 'touchstart' : 'click';

        Iterator.iterate(this._topLevelLinks, el => {
            el.addEventListener(openEvent, this._toggleNavbar.bind(this, el));
            el.addEventListener(closeEvent, this._toggleNavbar.bind(this, el));
            el.addEventListener(clickEvent, this._navigateToLinkOnClick.bind(this, el));
        });

    }

    _toggleNavbar(topLevelLink, event) {
        const currentDropdown = window.bootstrap.Dropdown.getOrCreateInstance(topLevelLink);
        if (event.type === 'mouseenter') {
            this._isMouseOver = true;
            this._debounce(() => {
                if (this._isMouseOver && currentDropdown?._menu && !currentDropdown._menu.classList.contains('show')) {
                    this._closeAllDropdowns();
                    this.$emitter.publish('closeAllDropdowns');
                    currentDropdown.show();
                    this.$emitter.publish('showDropdown');
                }
            }, this.options.debounceTime);
        } else if (event.type === 'mouseleave') {
            this._isMouseOver = false;
        }
    }

    _closeAllDropdowns() {
        const dropdowns = Array.from(this._topLevelLinks).map(link => window.bootstrap.Dropdown.getInstance(link));
        dropdowns.forEach(dropdown => {
            if (dropdown?._menu && dropdown._menu.classList.contains('show')) {
                dropdown.hide();
            }
        });
    }

    /**
     * Navigates to the link href on click
     * We can not use event.pageType to check if the event was triggered by mouse (always undefined in firefox).
     * So we check the event type and the pageX position (pageX is always 0 on touch devices and keyboard).
     * @param topLevelLink
     * @param event
     * @private
     */
    _navigateToLinkOnClick(topLevelLink, event) {
        if (event.type === 'click' && event.pageX !== 0) {
            event.preventDefault();
            event.stopPropagation();
            window.location.replace(topLevelLink.href);
        }
    }

    /**
     *
     * function to debounce menu
     * openings/closings
     *
     * @param {function} fn
     * @param {array} args
     *
     * @returns {Function}
     * @private
     */
    _debounce(fn, ...args) {
        this._clearDebounce();
        this._debouncer = setTimeout(fn.bind(this, ...args), this.options.debounceTime);
    }

    /**
     * clears the debounce timer
     *
     * @private
     */
    _clearDebounce() {
        clearTimeout(this._debouncer);
    }
}
