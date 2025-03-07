import Plugin from 'src/plugin-system/plugin.class';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';
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
        rootSelector: '.navigation-offcanvas-root',
        overlayHeadlineSelector: '.navigation-offcanvas-headline',
        initialContentSelector: '.js-navigation-offcanvas-initial-content',

        homeBtnClass: 'is-home-link',
        backBtnClass: 'is-back-link',
        transitionClass: 'has-transition',
        overlayClass: '.navigation-offcanvas-overlay',
        placeholderClass: '.navigation-offcanvas-placeholder',

        forwardAnimationType: 'forwards',
        backwardAnimationType: 'backwards',
    };

    init() {
        this._cache = {};
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
        OffCanvas.open(this._content, this._registerEvents.bind(this), this.options.position);
        OffCanvas.setAdditionalClassName(this.options.additionalOffcanvasClass);

        this.$emitter.publish('openMenu');
    }

    /**
     * returns the handler for the passed navigation link
     *
     * @param {Event} event
     * @param {Element} link
     * @private
     */
    _getLinkEventHandler(event, link) {

        // Initial root navigation
        if (!link) {
            const initialContentElement = document.querySelector(this.options.initialContentSelector);
            this._content = initialContentElement.innerHTML;

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

        let showOverlay = true;

        if (link.classList.contains(this.options.homeBtnClass) ||
            link.classList.contains(this.options.backBtnClass) && !url.includes('navigationId')) {
            showOverlay = false;
        }

        // Save the focus of the root menu link
        if (showOverlay && this._overlay?.classList.contains('d-none')) {
            window.focusHandler.saveFocusState('offcanvas-menu', link);
        }

        this.$emitter.publish('getLinkEventHandler');

        this._fetchMenu(url, this._updateOverlay.bind(this, showOverlay));
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
     * update the overlay content with the
     * subcategory navigation
     *
     * @param {boolean} showOverlay
     * @param {string} content
     * @private
     */
    _updateOverlay(showOverlay, content) {
        this._content = content;

        if (OffCanvas.exists()) {
            this._overlay = this._createOverlay(content);

            if (showOverlay) {
                this._showOverlay(this._overlay);
            } else {
                this._hideOverlay(this._overlay);
            }

            this._registerEvents();
        }

        this.$emitter.publish('updateOverlay');
    }

    _createOverlay(content) {
        const offCanvasMenu = OffcanvasMenuPlugin._getOffcanvasMenu();

        let overlay = offCanvasMenu.querySelector(this.options.overlayClass);

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.classList.add(this.options.overlayClass.substr(1));
            overlay.style.minHeight = `${offCanvasMenu.clientHeight}px`;
            offCanvasMenu.appendChild(overlay);
        }

        overlay.innerHTML = content;

        return overlay;
    }

    _showOverlay(overlay) {
        const offcanvasContainer = OffcanvasMenuPlugin._getOffcanvasMenu();
        const rootMenu = offcanvasContainer.querySelector(this.options.rootSelector);

        rootMenu.classList.add('d-none');
        overlay.classList.remove('d-none');

        // Focus the headline with the main category to which the sub-menu belongs.
        const headline = overlay.querySelector(this.options.overlayHeadlineSelector);
        window.focusHandler.setFocus(headline);

        this.$emitter.publish('showOverlay');
    }

    _hideOverlay(overlay) {
        const offcanvasContainer = OffcanvasMenuPlugin._getOffcanvasMenu();
        const rootMenu = offcanvasContainer.querySelector(this.options.rootSelector);

        rootMenu.classList.remove('d-none');
        overlay.classList.add('d-none');

        window.focusHandler.resumeFocusState('offcanvas-menu', { focusVisible: true });

        this.$emitter.publish('hideOverlay');
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

        this._client.get(link, (res) => {
            this._cache[link] = res;
            if (typeof cb === 'function') {
                cb(res);
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
