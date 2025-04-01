import Plugin from 'src/plugin-system/plugin.class';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * @sw-package framework
 */
export default class OffcanvasMenuPlugin extends Plugin {

    static options = {
        navigationUrl: window.router['frontend.menu.offcanvas'],
        position: 'left',
        triggerEvent: 'click',

        additionalOffcanvasClass: 'navigation-offcanvas',
        linkSelector: '.js-navigation-offcanvas-link',
        loadingIconSelector: '.js-navigation-offcanvas-loading-icon',
        linkLoadingClass: 'is-loading',
        menuSelector: '.navigation-offcanvas-container',
        initialContentSelector: '.js-navigation-offcanvas-initial-content',
        currentCategorySelector: 'a.is-current-category',
    };

    init() {
        this._cache = {};

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._client = new HttpClient();
        this._content = LoadingIndicator.getTemplate();

        this._registerEvents();
    }

    /**
     * register triggers
     *
     * @private
     */
    _registerEvents() {
        this.el.removeEventListener(this.options.triggerEvent, this._getLinkEventHandler.bind(this));
        this.el.addEventListener(this.options.triggerEvent, this._getLinkEventHandler.bind(this));

        if (OffCanvas.exists()) {
            const offCanvasElements = OffCanvas.getOffCanvas();

            offCanvasElements.forEach(offcanvas => {
                const links = offcanvas.querySelectorAll(this.options.linkSelector);
                links.forEach(link => {
                    OffcanvasMenuPlugin._resetLoader(link);
                    link.addEventListener('click', (event) => {
                        this._getLinkEventHandler(event, link);
                    });
                });
            });
            // initialize the plugins again, after Off-Canvas init, otherwise you will miss the JS event listener
            window.PluginManager.initializePlugins();
        }
        // re-open the menu if the url parameter is set
        this._openMenuViaUrlParameter();
    }

    /**
     * opens the offcanvas menu
     *
     * @param event
     * @private
     */
    _openMenu(event) {
        OffcanvasMenuPlugin._stopEvent(event);
        OffCanvas.open(this._content, this._registerEvents.bind(this), this.options.position);
        OffCanvas.setAdditionalClassName(this.options.additionalOffcanvasClass);

        this.$emitter.publish('openMenu');
    }

    /**
     * opens the menu via url parameter (offcanvas=menu)
     * @private
     */
    _openMenuViaUrlParameter() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('offcanvas') && urlParams.get('offcanvas') === 'menu') {
            document.querySelector('[data-off-canvas-menu="true"]')?.click();
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('offcanvas');
                window.history.replaceState({}, document.title, url);
            }
        }
    }

    /**
     * returns the handler for the passed navigation link
     *
     * @param {Event} event
     * @param {Element} link
     * @private
     */
    _getLinkEventHandler(event, link) {

        // Initial navigation
        if (!link) {
            const initialContentElement = document.querySelector(this.options.initialContentSelector);
            const url = `${this.options.navigationUrl}?navigationId=${window.activeNavigationId}`;

            return this._fetchMenu(url, (htmlResponse) => {
                const navigationContainer = initialContentElement.querySelector(this.options.menuSelector);
                navigationContainer.innerHTML = htmlResponse;

                this._content = initialContentElement.innerHTML;

                return this._openMenu(event);
            });
        }

        OffcanvasMenuPlugin._stopEvent(event);
        if (link.classList.contains(this.options.linkLoadingClass)) {
            return;
        }

        OffcanvasMenuPlugin._setLoader(link);

        const url = link.getAttribute('data-href') || link.getAttribute('href');

        if (!url) {
            return;
        }

        this.$emitter.publish('getLinkEventHandler');

        this._fetchMenu(url, this._updateContent.bind(this));
    }

    /**
     * sets the loader on the navigation link
     *
     * @param link
     * @private
     */
    static _setLoader(link) {
        link.classList.add(this.options.linkLoadingClass);
        const icon = link.querySelector(this.options.loadingIconSelector);

        if (icon) {
            icon._linkIcon = icon.innerHTML;
            icon.innerHTML = LoadingIndicator.getTemplate();
        }
    }

    /**
     * resets a loader to a navigation link
     *
     * @param link
     * @private
     */
    static _resetLoader(link) {
        link.classList.remove(this.options.linkLoadingClass);
        const icon = link.querySelector(this.options.loadingIconSelector);
        if (icon && icon._linkIcon) {
            icon.innerHTML = icon._linkIcon;
        }
    }

    /**
     * Update the content with the current navigation.
     *
     * @param {string} content
     * @private
     */
    _updateContent(content) {
        this._content = content;

        if (OffCanvas.exists()) {
            const container = OffcanvasMenuPlugin._getOffcanvasMenu();

            container.innerHTML = content;

            // Focus the current category
            const currentCategory = container.querySelector(this.options.currentCategorySelector);
            window.focusHandler.setFocus(currentCategory, { focusVisible: true });

            this._registerEvents();
        }

        this.$emitter.publish('updateContent');
    }

    /**
     * fetch the menu content
     *
     * @param link
     * @param cb
     * @private
     */
    _fetchMenu(link, cb) {
        if (!link) {
            return false;
        }

        if (this._cache[link]) {
            if (typeof cb === 'function') {
                return cb(this._cache[link]);
            }
        }

        this.$emitter.publish('beforeFetchMenu');

        fetch(link, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => res.text())
            .then(content => {
                this._cache[link] = content;
                if (typeof cb === 'function') {
                    cb(content);
                }
            });
    }

    /**
     * @param {Event} event
     * @private
     */
    static _stopEvent(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }

    /**
     * returns the offcanvas element
     *
     * @returns {Node}
     * @private
     */
    static _getOffcanvas() {
        return OffCanvas.getOffCanvas()[0];
    }

    /**
     * returns the offcanvas main menu element
     *
     * @returns {Element|any}
     * @private
     */
    static _getOffcanvasMenu() {
        const offcanvas = OffcanvasMenuPlugin._getOffcanvas();

        return offcanvas.querySelector(this.options.menuSelector);
    }
}
