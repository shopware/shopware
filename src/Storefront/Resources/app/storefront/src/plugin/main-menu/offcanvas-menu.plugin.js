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
        if (!window.Feature.isActive('v6.8.0.0')) {
            this._client = new HttpClient();
        }
        this._content = LoadingIndicator.getTemplate();
        this._currentUrl = null;
        this._offcanvas = null;
        this._boundLinks = new WeakSet();

        this._registerEvents();
        document.addEventListener('sw-twig-hmr:updated', this._onTwigHmrUpdated.bind(this));
    }

    /**
     * register triggers
     *
     * @private
     */
    _registerEvents() {
        if (!this._boundGetLinkEventHandler) {
            this._boundGetLinkEventHandler = this._getLinkEventHandler.bind(this);
        }

        this.el.removeEventListener(this.options.triggerEvent, this._boundGetLinkEventHandler);
        this.el.addEventListener(this.options.triggerEvent, this._boundGetLinkEventHandler);

        if (OffCanvas.exists()) {
            const offCanvasElements = OffCanvas.getOffCanvas();

            offCanvasElements.forEach(offcanvas => {
                this._registerLinkEvents(offcanvas);

                window.PluginManager.initializePluginsInParentElement(offcanvas);
            });
        }
        // re-open the menu if the url parameter is set
        this._openMenuViaUrlParameter();
    }

    /**
     * @param {Element} offcanvas
     * @private
     */
    _registerLinkEvents(offcanvas) {
        const links = offcanvas.querySelectorAll(this.options.linkSelector);
        links.forEach(link => {
            OffcanvasMenuPlugin._resetLoader(link);

            // the Twig hot reload keeps links that did not change, and these already have a listener
            if (this._boundLinks.has(link)) {
                return;
            }

            this._boundLinks.add(link);
            link.addEventListener('click', (event) => {
                this._getLinkEventHandler(event, link);
            });
        });
    }

    /**
     * The menu is a part of the page with the fetched navigation inside.
     * These attributes let the Twig hot reload of the watcher render the open menu again.
     *
     * @private
     */
    _setSource() {
        const offcanvas = OffcanvasMenuPlugin._getOffcanvas();

        if (!offcanvas || !this._currentUrl) {
            return;
        }

        this._offcanvas = offcanvas;
        offcanvas.dataset.swSourceShell = this.options.initialContentSelector;
        offcanvas.dataset.swSourceSlot = this.options.menuSelector;
        offcanvas.dataset.swSourceUrl = this._currentUrl;
    }

    /**
     * Cached responses hold the templates from before the update, and the update may add new links.
     *
     * @private
     */
    _onTwigHmrUpdated() {
        this._cache = {};

        // only the plugin that opened the menu handles its links
        if (this._offcanvas?.isConnected) {
            this._registerLinkEvents(this._offcanvas);
        }
    }

    /**
     * opens the offcanvas menu
     *
     * @param event
     * @private
     */
    _openMenu(event) {
        OffcanvasMenuPlugin._stopEvent(event);
        OffCanvas.open(this._content, () => {
            this._setSource();
            this._registerEvents();
        }, this.options.position);
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
            this._currentUrl = url;

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

        this._currentUrl = url;
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

            this._setSource();
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
