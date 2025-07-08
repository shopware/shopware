import Plugin from 'src/plugin-system/plugin.class';
import DeviceDetection from 'src/helper/device-detection.helper';

export default class NavbarPlugin extends Plugin {
    static options = {
        /**
         * Hover debounce delay.
         */
        debounceTime: 125,
        /**
         * Class to select the main navigation items, which contain both the top level link and the dropdown navigation.
         */
        navItemSelector: '.nav-item',
        /**
         * Class to select the top level links.
         */
        topLevelLinksSelector: '.main-navigation-link',
        /**
         * Class to select the current page to add aria label current page to it.
         */
        ariaCurrentPageSelector: '.nav-item-{id}-link',

        /**
         * Class to show the currently active category.
         */
        activeClass: 'active',

        /**
         * Array of ids representing the path to the currently active category.
         */
        pathIdList: [],
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

        this.el.addEventListener('mouseleave', this._closeAllDropdowns.bind(this));
        this.el.addEventListener('focusout', this._restoreFocusAfterBtnClose.bind(this));

        this._topLevelLinks.forEach(el => {
            el.addEventListener(openEvent, this._toggleNavbar.bind(this, el));
            el.addEventListener(closeEvent, this._toggleNavbar.bind(this, el));
            if (el.getAttribute('href') !== null) {
                el.addEventListener(clickEvent, this._navigateToLinkOnClick.bind(this, el));
            }
        });

        window.addEventListener('load', () => {
            this._setCurrentPage();
        });
    }

    _toggleNavbar(topLevelLink, event) {
        const currentDropdown = window.bootstrap.Dropdown.getOrCreateInstance(topLevelLink);
        if (event.type === 'mouseenter') {
            this._isMouseOver = true;
            this._debounce(() => {
                if (this._isMouseOver && currentDropdown?._menu && !currentDropdown._menu.classList.contains('show')) {
                    this._closeAllDropdowns();
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

        this.$emitter.publish('closeAllDropdowns');
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
            if (topLevelLink.target === '_blank') {
                window.open(topLevelLink.href, '_blank', 'noopener, noreferrer');
                return;
            }
            window.location.href = topLevelLink.href;
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

    /**
     * Sets the active class and aria-current attribute on the configured selectors.
     * @private
     */
    _setCurrentPage() {
        if (window.activeNavigationId) {
            const navItemSelector = this.options.ariaCurrentPageSelector.replace('{id}', window.activeNavigationId);
            const activeNavItem = this.el.querySelector(navItemSelector);

            if (activeNavItem) {
                activeNavItem.setAttribute('aria-current', 'page');
                activeNavItem.classList.add(this.options.activeClass);
            }
        }

        this.options.pathIdList.forEach((id) => {
            const navItemSelector = this.options.ariaCurrentPageSelector.replace('{id}', id);
            const activeNavItem = this.el.querySelector(navItemSelector);

            if (activeNavItem) {
                activeNavItem.classList.add(this.options.activeClass);
            }
        });
    }

    /**
     * Restores focus to the main-navigation link related to the currently active dropdown navigation.
     * The focus state is lost when closing the dropdown via button using a keyboard.
     *
     * @param {FocusEvent} event
     * @return {void}
     */
    _restoreFocusAfterBtnClose(event) {
        if (event.relatedTarget || event.target.matches(this.options.topLevelLinksSelector)) {
            return;
        }

        const link = event.target.closest(this.options.navItemSelector)?.querySelector(this.options.topLevelLinksSelector);

        if (!link) {
            return;
        }

        window.focusHandler.setFocus(link);
    }
}
