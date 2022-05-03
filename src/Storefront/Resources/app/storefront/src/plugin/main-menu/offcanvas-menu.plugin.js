import Plugin from 'src/plugin-system/plugin.class';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';
import HttpClient from 'src/service/http-client.service';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import ViewportDetection from 'src/helper/viewport-detection.helper';

export default class OffcanvasMenuPlugin extends Plugin {

    static options = {
        navigationUrl: window.router['frontend.menu.offcanvas'],
        position: 'left',
        tiggerEvent: 'click',

        additionalOffcanvasClass: 'navigation-offcanvas',
        linkSelector: '.js-navigation-offcanvas-link',
        loadingIconSelector: '.js-navigation-offcanvas-loading-icon',
        linkLoadingClass: 'is-loading',
        menuSelector: '.js-navigation-offcanvas',
        overlayContentSelector: '.js-navigation-offcanvas-overlay-content',
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
        this.el.removeEventListener(this.options.tiggerEvent, this._getLinkEventHandler.bind(this));
        this.el.addEventListener(this.options.tiggerEvent, this._getLinkEventHandler.bind(this));

        if (OffCanvas.exists()) {
            const offCanvasElements = OffCanvas.getOffCanvas();

            Iterator.iterate(offCanvasElements, offcanvas => {
                const links = offcanvas.querySelectorAll(this.options.linkSelector);
                Iterator.iterate(links, link => {
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
        const isFullwidth = ViewportDetection.isXS();
        OffcanvasMenuPlugin._stopEvent(event);
        OffCanvas.open(this._content, this._registerEvents.bind(this), this.options.position, undefined, undefined, isFullwidth);
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
        if (!link) {
            const initialContentElement = DomAccess.querySelector(document, this.options.initialContentSelector);
            this._content = initialContentElement.innerHTML;

            if (initialContentElement.classList.contains('is-root')) {
                this._cache[this.options.navigationUrl] = this._content;
            } else {
                // fetch home menu to warm the cache
                this._fetchMenu(this.options.navigationUrl);
            }

            return this._openMenu(event);
        }

        OffcanvasMenuPlugin._stopEvent(event);
        if (link.classList.contains(this.options.linkLoadingClass)) {
            return;
        }

        OffcanvasMenuPlugin._setLoader(link);

        const url = DomAccess.getAttribute(link, 'data-href', false) || DomAccess.getAttribute(link, 'href', false);

        if (!url) {
            return;
        }

        let animationType = this.options.forwardAnimationType;
        if (link.classList.contains(this.options.homeBtnClass) || link.classList.contains(this.options.backBtnClass)) {
            animationType = this.options.backwardAnimationType;
        }

        this.$emitter.publish('getLinkEventHandler');

        this._fetchMenu(url, this._updateOverlay.bind(this, animationType));
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
     * @param {string} animationType
     * @param {string} content
     * @private
     */
    _updateOverlay(animationType, content) {
        this._content = content;

        if (OffCanvas.exists()) {
            const offcanvasMenu = OffcanvasMenuPlugin._getOffcanvasMenu();

            // if there is no content present
            // insert the whole response into the offcanvas
            if (!offcanvasMenu) {
                this._replaceOffcanvasContent(content);
            }

            this._createOverlayElements();
            const currentContent = OffcanvasMenuPlugin._getOverlayContent(offcanvasMenu);
            const menuContent = OffcanvasMenuPlugin._getMenuContentFromResponse(content);

            this._replaceOffcanvasMenuContent(animationType, menuContent, currentContent);
            this._registerEvents();
        }

        this.$emitter.publish('updateOverlay');
    }

    /**
     * grab only the menu from the response
     * and replace it within the created
     * menu elements
     *
     * @param animationType
     * @param menuContent
     * @param currentContent
     * @private
     */
    _replaceOffcanvasMenuContent(animationType, menuContent, currentContent) {

        if (animationType === this.options.forwardAnimationType) {
            this._animateForward(menuContent, currentContent);
            return;
        }

        if (animationType === this.options.backwardAnimationType) {
            this._animateBackward(menuContent, currentContent);
            return;
        }

        this._animateInstant(menuContent, currentContent);

        this.$emitter.publish('replaceOffcanvasMenuContent');
    }

    /**
     * instantly replaces the ovleray content
     *
     * @param menuContent
     * @private
     */
    _animateInstant(menuContent) {
        this._overlay.innerHTML = menuContent;

        this.$emitter.publish('animateInstant');
    }

    /**
     * replaces the content and
     * animates the overlay to slide in
     *
     * @param menuContent
     * @param currentContent
     * @private
     */
    _animateForward(menuContent, currentContent) {
        if (this._placeholder.innerHTML === '') {
            this._placeholder.innerHTML = currentContent;
        }
        this._overlay.classList.remove(this.options.transitionClass);
        this._overlay.style.left = '100%';
        this._overlay.innerHTML = menuContent;
        setTimeout(() => {
            this._overlay.classList.add(this.options.transitionClass);
            this._overlay.style.left = '0%';
        }, 1);

        this.$emitter.publish('animateForward');
    }

    /**
     * replaces the content and
     * animates the overlay to slide out
     *
     * @param menuContent
     * @param currentContent
     * @private
     */
    _animateBackward(menuContent, currentContent) {
        if (this._overlay.innerHTML === '') {
            this._overlay.innerHTML = currentContent;
        }
        this._placeholder.innerHTML = menuContent;
        this._overlay.classList.remove(this.options.transitionClass);
        this._overlay.style.left = '0%';
        setTimeout(() => {
            this._overlay.classList.add(this.options.transitionClass);
            this._overlay.style.left = '100%';
        }, 1);

        this.$emitter.publish('animateBackward');
    }

    /**
     * returns the menu content
     * form the complete offcanvas response
     *
     * @param content
     * @returns {string}
     * @private
     */
    static _getMenuContentFromResponse(content) {
        const html = new DOMParser().parseFromString(content, 'text/html');
        return OffcanvasMenuPlugin._getOverlayContent(html);
    }

    /**
     * returns the content
     * from the overlay content container
     *
     * @param element
     * @returns {string}
     * @private
     */
    static _getOverlayContent(element) {
        if (!element) {
            return '';
        }

        const contentElement = element.querySelector(this.options.overlayContentSelector);
        if (!contentElement) {
            return '';
        }

        return contentElement.innerHTML;
    }

    /**
     * creates the overlay
     * elements to be able to animate the menu
     *
     * @private
     */
    _createOverlayElements() {
        const offcanvasMenu = OffcanvasMenuPlugin._getOffcanvasMenu();

        if (offcanvasMenu) {
            this._placeholder = OffcanvasMenuPlugin._createPlaceholder(offcanvasMenu);
            this._overlay = OffcanvasMenuPlugin._createNavigationOverlay(offcanvasMenu);
        }

        this.$emitter.publish('createOverlayElements');
    }

    /**
     * @param {HTMLElement} container
     *
     * @returns {HTMLElement}
     * @private
     */
    static _createNavigationOverlay(container) {
        const offcanvas = OffcanvasMenuPlugin._getOffcanvas();
        const currentOverlay = offcanvas.querySelector(this.options.overlayClass);
        if (currentOverlay) {
            return currentOverlay;
        }

        const overlay = document.createElement('div');
        overlay.classList.add(this.options.overlayClass.substr(1));
        overlay.style.minHeight = `${offcanvas.clientHeight}px`;
        container.appendChild(overlay);

        return overlay;
    }

    /**
     * @param {HTMLElement} container
     *
     * @returns {HTMLElement}
     * @private
     */
    static _createPlaceholder(container) {
        const offcanvas = OffcanvasMenuPlugin._getOffcanvas();
        const currentPlaceholder = offcanvas.querySelector(this.options.placeholderClass);
        if (currentPlaceholder) {
            return currentPlaceholder;
        }

        const placeholder = document.createElement('div');
        placeholder.classList.add(this.options.placeholderClass.substr(1));
        placeholder.style.minHeight = `${offcanvas.clientHeight}px`;
        container.appendChild(placeholder);

        return placeholder;
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
     * replaces the offcanvas content
     *
     * @param {string} content
     * @private
     */
    _replaceOffcanvasContent(content) {
        this._content = content;
        OffCanvas.setContent(this._content);
        this._registerEvents();

        this.$emitter.publish('replaceOffcanvasContent');
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
