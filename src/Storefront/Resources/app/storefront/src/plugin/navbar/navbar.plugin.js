import DeviceDetection from 'src/helper/device-detection.helper';
import Plugin from 'src/plugin-system/plugin.class';

export default class NavbarPlugin extends Plugin {
    static options = {
        /**
         * Hover debounce delay.
         */
        debounceTime: 200,
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
         * Only used when `window.activeNavigationPathIdList` is not set.
         * The navbar is ESI-cached, so a value rendered into the navbar markup can belong to another page.
         */
        pathIdList: [],
    };

    init() {
        this._topLevelLinks = this.el.querySelectorAll(`${this.options.topLevelLinksSelector}`);
        this._registerEvents();
        this._isMouseOver = false;
        this._setCurrentPage();
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
    }

    _toggleNavbar(topLevelLink, event) {
        const currentDropdown = window.bootstrap.Dropdown.getOrCreateInstance(topLevelLink);
        if (event.type === 'mouseenter') {
            this._isMouseOver = true;
            this._debounce(() => {
                if (this._isMouseOver) {
                    this._closeAllDropdowns();

                    if (currentDropdown?._menu && !currentDropdown._menu.classList.contains('show')) {
                        currentDropdown.show();
                        topLevelLink.blur();
                    }

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
     *
     * Since top level links lose the ability to be a link when a dropdown is attached, we enforce redirection here manually.
     *
     * @param topLevelLink
     * @param event
     * @private
     */
    _navigateToLinkOnClick(topLevelLink, event) {
        if (event.type === 'click' && event.pageX !== 0) {
            // Only dropdown links lose their native navigation; plain links are handled by the browser.
            if (!topLevelLink.classList.contains('dropdown-toggle')) {
                return;
            }

            if (topLevelLink.target === '_blank') {
                window.open(topLevelLink.href, '_blank', 'noopener, noreferrer');
                return;
            }

            this._navigateTo(topLevelLink.href);
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
        let activeNavItem = null;

        if (window.activeNavigationId) {
            const navItemSelector = this.options.ariaCurrentPageSelector.replace('{id}', window.activeNavigationId);
            activeNavItem = this.el.querySelector(navItemSelector);

            if (activeNavItem) {
                activeNavItem.setAttribute('aria-current', 'page');
                activeNavItem.classList.add(this.options.activeClass);
            }
        }

        const pathIdList = this._getActivePathIdList();

        if (pathIdList === null) {
            this._setActiveTopLevelLinkFromDom(activeNavItem);

            return;
        }

        pathIdList.forEach((id) => {
            const navItemSelector = this.options.ariaCurrentPageSelector.replace('{id}', id);
            const activeNavItem = this.el.querySelector(navItemSelector);

            if (activeNavItem) {
                activeNavItem.classList.add(this.options.activeClass);
            }
        });
    }

    /**
     * Returns the ids of the parent categories of the current page, or null if no source provides them.
     *
     * `window.activeNavigationPathIdList` is rendered per request and wins over the `pathIdList` option.
     *
     * @returns {string[]|null}
     * @private
     */
    _getActivePathIdList() {
        if (Array.isArray(window.activeNavigationPathIdList)) {
            return window.activeNavigationPathIdList;
        }

        if (Array.isArray(this.options.pathIdList) && this.options.pathIdList.length > 0) {
            console.warn('[NavbarPlugin] Using the pathIdList option. Set window.activeNavigationPathIdList for ESI-safe navigation.');

            return this.options.pathIdList;
        }

        return null;
    }

    /**
     * Fallback for themes that replace the block rendering `window.activeNavigationPathIdList`:
     * marks the top level link whose dropdown contains the current page as active.
     *
     * @param {HTMLElement|null} activeNavItem
     * @private
     */
    _setActiveTopLevelLinkFromDom(activeNavItem) {
        if (!activeNavItem || !this._topLevelLinks) {
            return;
        }

        console.warn('[NavbarPlugin] Deriving the active navigation from the DOM. Set window.activeNavigationPathIdList for the full path.');

        const topLevelLink = Array.from(this._topLevelLinks).find((link) => {
            return link !== activeNavItem && link.closest(this.options.navItemSelector)?.contains(activeNavItem);
        });

        topLevelLink?.classList.add(this.options.activeClass);
    }

    /**
     * Thin wrapper so tests can spy on navigation without mocking window.location
     * (non-configurable in JSDOM v26).
     */
    _navigateTo(url) {
        window.location.href = url;
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
