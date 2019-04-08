import Plugin from 'asset/script/helper/plugin/plugin.class';
import OffCanvas from 'asset/script/plugin/off-canvas/offcanvas.plugin';
import LoadingIndicator from 'asset/script/util/loading-indicator/loading-indicator.util';
import HttpClient from 'asset/script/service/http-client.service';
import DomAccess from 'asset/script/helper/dom-access.helper';

const NAVIGATION_URL = window.router['widgets.menu.offcanvas'];
const CURRENT_NAVIGATION_URL = `${NAVIGATION_URL}?navigationId=${window.activeNavigationId}`;

const POSITION = 'left';
const TRIGGER_EVENT = 'click';

const ADDITIONAL_OFFCANVAS_CLASS = 'offcanvas-menu';

const LINK_SELECTOR = '.js-offcanvas-menu-link';
const LOADING_ICON_SELECTOR = '.js-offcanvas-menu-loading-icon';
const MENU_SELECTOR = '.js-offcanvas-menu';
const OVERLAY_CONTENT_SELECTOR = '.js-offcanvas-menu-overlay-content';

const HOME_BTN_CLASS = 'go-home';
const BACK_BTN_CLASS = 'go-back';
const TRANSITION_CLASS = 'has-transition';
const OVERLAY_CLASS = '.offcanvas-menu-overlay';
const PLACEHOLDER_CLASS = '.offcanvas-menu-placeholder';

const FORWARD_ANIMATION_TYPE = 'forwards';
const BACKWARD_ANIMATION_TYPE = 'backwards';
const INSTANT_ANIMATION_TYPE = 'instant';

export default class OffcanvasMenuPlugin extends Plugin {

    init() {
        this._cache = {};
        this._client = new HttpClient(window.accessKey, window.contextToken);
        this._content = LoadingIndicator.getTemplate();

        // always fetch home menu to warm the cache
        this._fetchMenu(NAVIGATION_URL);
        // fetch current menu
        this._fetchMenu(CURRENT_NAVIGATION_URL, this._updateOverlay.bind(this, INSTANT_ANIMATION_TYPE));

        this._registerEvents();
    }

    /**
     * register triggers
     *
     * @private
     */
    _registerEvents() {
        this.el.removeEventListener(TRIGGER_EVENT, this._openMenu.bind(this));
        this.el.addEventListener(TRIGGER_EVENT, this._openMenu.bind(this));

        if (OffCanvas.exists()) {
            const offcanvasElements = OffCanvas.getOffCanvas();

            offcanvasElements.forEach((offcanvas) => {
                const links = offcanvas.querySelectorAll(LINK_SELECTOR);
                links.forEach(link => {
                    OffcanvasMenuPlugin._resetLoader(link);
                    link.addEventListener('click', (event) => {
                        this._getLinkEventHandler(event, link);
                    });
                });
            })
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
        OffCanvas.open(this._content, this._registerEvents.bind(this), POSITION);
        OffCanvas.setAdditionalClassName(ADDITIONAL_OFFCANVAS_CLASS);
    }

    /**
     * returns the handler for the passed navigation link
     *
     * @param {Event} event
     * @param {Element} link
     * @private
     */
    _getLinkEventHandler(event, link) {
        OffcanvasMenuPlugin._stopEvent(event);

        const url = DomAccess.getAttribute(link, 'href', true);
        OffcanvasMenuPlugin._setLoader(link);

        let animationType = FORWARD_ANIMATION_TYPE;
        if (link.classList.contains(HOME_BTN_CLASS) || link.classList.contains(BACK_BTN_CLASS)) {
            animationType = BACKWARD_ANIMATION_TYPE;
        }

        this._fetchMenu(url, this._updateOverlay.bind(this, animationType));
    }

    /**
     * sets the loader on the navigation link
     *
     * @param link
     * @private
     */
    static _setLoader(link) {
        const icon = link.querySelector(LOADING_ICON_SELECTOR);
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
        const icon = link.querySelector(LOADING_ICON_SELECTOR);
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

        if (animationType === FORWARD_ANIMATION_TYPE) {
            this._animateForward(menuContent, currentContent);
            return;
        }

        if (animationType === BACKWARD_ANIMATION_TYPE) {
            this._animateBackward(menuContent, currentContent);
            return;
        }

        this._animateInstant(menuContent, currentContent);
    }

    /**
     * instantly replaces the ovleray content
     *
     * @param menuContent
     * @private
     */
    _animateInstant(menuContent) {
        this._overlay.innerHTML = menuContent;
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
        this._overlay.classList.remove(TRANSITION_CLASS);
        this._overlay.style.left = '100%';
        this._overlay.innerHTML = menuContent;
        setTimeout(() => {
            this._overlay.classList.add(TRANSITION_CLASS);
            this._overlay.style.left = '0%';
        }, 1);
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
        this._overlay.classList.remove(TRANSITION_CLASS);
        this._overlay.style.left = '0%';
        setTimeout(() => {
            this._overlay.classList.add(TRANSITION_CLASS);
            this._overlay.style.left = '100%';
        }, 1);
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

        const contentElement = element.querySelector(OVERLAY_CONTENT_SELECTOR);
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
    }

    /**
     * @param {HTMLElement} container
     *
     * @returns {HTMLElement}
     * @private
     */
    static _createNavigationOverlay(container) {
        const offcanvas = OffcanvasMenuPlugin._getOffcanvas();
        const currentOverlay = offcanvas.querySelector(OVERLAY_CLASS);
        if (currentOverlay) {
            return currentOverlay;
        }

        const overlay = document.createElement('div');
        overlay.classList.add(OVERLAY_CLASS.substr(1));
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
        const currentPlaceholder = offcanvas.querySelector(PLACEHOLDER_CLASS);
        if (currentPlaceholder) {
            return currentPlaceholder;
        }

        const placeholder = document.createElement('div');
        placeholder.classList.add(PLACEHOLDER_CLASS.substr(1));
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

        return offcanvas.querySelector(MENU_SELECTOR);
    }

}

