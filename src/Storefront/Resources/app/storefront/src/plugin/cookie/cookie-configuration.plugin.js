/**
 *
 * CookieConfiguration plugin
 * --------------------------
 * Renders the configuration template inside an offCanvas
 *
 * Applies its "openOffCanvas"-eventHandler to the following selector:
 * 1) '.js-cookie-configuration-button button'
 * 2) `[href="${window.router['frontend.cookie.offcanvas']}"]`
 *
 * Can be opened manually using the public method "openOffCanvas"
 *
 * The cookie form is defined via CookieController.php
 * Cookies marked as "required" (see CookieController.php) are ignored, since they are assumed to be set manually
 *
 * Configuration changes are pushed to the global (document) event "CookieConfiguration_Update"
 *
 */

/* global PluginManager */

import Plugin from 'src/plugin-system/plugin.class';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

// These events will be published via a global (document) EventEmitter
export const COOKIE_CONFIGURATION_UPDATE = 'CookieConfiguration_Update';
export const COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS = 'CookieConfiguration_CloseOffCanvas';

export default class CookieConfiguration extends Plugin {

    static options = {
        offCanvasPosition: 'left',
        submitEvent: 'click',
        cookiePreference: 'cookie-preference',
        cookieSelector: '[data-cookie]',
        buttonOpenSelector: '.js-cookie-configuration-button button',
        buttonSubmitSelector: '.js-offcanvas-cookie-submit',
        buttonAcceptAllSelector: '.js-offcanvas-cookie-accept-all',
        globalButtonAcceptAllSelector: '.js-cookie-accept-all-button',
        wrapperToggleSelector: '.offcanvas-cookie-entries span',
        parentInputSelector: '.offcanvas-cookie-parent-input',
        customLinkSelector: `[href="${window.router['frontend.cookie.offcanvas']}"]`,
        entriesActiveClass: 'offcanvas-cookie-entries--active',
        entriesClass: 'offcanvas-cookie-entries',
        groupClass: 'offcanvas-cookie-group',
        parentInputClass: 'offcanvas-cookie-parent-input',
    };

    init() {
        this.lastState = {
            active: [],
            inactive: [],
        };

        this._httpClient = new HttpClient();

        this._registerEvents();
    }

    /**
     * Registers the events for displaying the offCanvas
     * Applies the event to all elements using the "buttonOpenSelector" or "customLinkSelector"
     *
     * @private
     */
    _registerEvents() {
        const { submitEvent, buttonOpenSelector, customLinkSelector, globalButtonAcceptAllSelector } = this.options;

        Array.from(document.querySelectorAll(buttonOpenSelector)).forEach(button => {
            button.addEventListener(submitEvent, this.openOffCanvas.bind(this));
        });

        Array.from(document.querySelectorAll(customLinkSelector)).forEach(customLink => {
            customLink.addEventListener(submitEvent, this._handleCustomLink.bind(this));
        });

        Array.from(document.querySelectorAll(globalButtonAcceptAllSelector)).forEach(customLink => {
            customLink.addEventListener(submitEvent, this._acceptAllCookiesFromCookieBar.bind(this));
        });
    }

    /**
     * Registers events required by the offCanvas template
     *
     * @private
     */
    _registerOffCanvasEvents() {
        const { submitEvent, buttonSubmitSelector, buttonAcceptAllSelector, wrapperToggleSelector } = this.options;
        const offCanvas = this._getOffCanvas();

        if (offCanvas) {
            const button = offCanvas.querySelector(buttonSubmitSelector);
            const buttonAcceptAll = offCanvas.querySelector(buttonAcceptAllSelector);
            const checkboxes = Array.from(offCanvas.querySelectorAll('input[type="checkbox"]'));
            const wrapperTrigger = Array.from(offCanvas.querySelectorAll(wrapperToggleSelector));

            if (button) {
                button.addEventListener(submitEvent, this._handleSubmit.bind(this, CookieStorage));
            }

            if (buttonAcceptAll) {
                buttonAcceptAll.addEventListener(submitEvent, this._acceptAllCookiesFromOffCanvas.bind(this, CookieStorage));
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener(submitEvent, this._handleCheckbox.bind(this));
            });

            wrapperTrigger.forEach(trigger => {
                trigger.addEventListener(submitEvent, this._handleWrapperTrigger.bind(this));
            });
        }
    }

    /**
     * Prevent the event default e.g. for anchor elements using the href-selector
     *
     * @param event
     * @private
     */
    _handleCustomLink(event) {
        event.preventDefault();

        this.openOffCanvas();
    }

    _handleUpdateListener(active, inactive) {
        const updatedCookies = this._getUpdatedCookies(active, inactive);

        document.$emitter.publish(COOKIE_CONFIGURATION_UPDATE, updatedCookies);
    }

    /**
     * Compare the current in-/active cookies to the initialState and return updated cookies only
     *
     * @param active
     * @param inactive
     * @private
     */
    _getUpdatedCookies(active, inactive) {
        const { lastState } = this;
        const updated = {};

        active.forEach(currentCheckbox => {
            if (lastState.inactive.includes(currentCheckbox)) {
                updated[currentCheckbox] = true;
            }
        });

        inactive.forEach(currentCheckbox => {
            if (lastState.active.includes(currentCheckbox)) {
                updated[currentCheckbox] = false;
            }
        });

        return updated;
    }

    /**
     * Public method to open the offCanvas
     *
     * @param {function|null} callback
     */
    openOffCanvas(callback) {
        const { offCanvasPosition } = this.options;
        const url = window.router['frontend.cookie.offcanvas'];

        this._hideCookieBar();

        AjaxOffCanvas.open(url, false, this._onOffCanvasOpened.bind(this, callback), offCanvasPosition);
    }

    /**
     * Public method to close the offCanvas
     *
     * @param callback
     */
    closeOffCanvas(callback) {
        AjaxOffCanvas.close();

        if (typeof callback === 'function') {
            callback();
        }
    }

    /**
     * Private method to apply events to the cookie-configuration template
     * Also sets the initial checkbox state based on currently set cookies
     *
     * @private
     */
    _onOffCanvasOpened(callback) {
        this._registerOffCanvasEvents();
        this._setInitialState();
        this._setInitialOffcanvasState();
        PluginManager.initializePlugins();

        if (typeof callback === 'function') {
            callback();
        }
    }

    _hideCookieBar() {
        const cookiePermissionPlugin = PluginManager.getPluginInstances('CookiePermission');

        if (cookiePermissionPlugin && cookiePermissionPlugin[0]) {
            cookiePermissionPlugin[0]._hideCookieBar();
            cookiePermissionPlugin[0]._removeBodyPadding();
        }
    }

    /**
     * Sets the `lastState` of the current cookie configuration, either passed as
     * parameter `cookies`, otherwise it is loaded by parsing the DOM of the off
     * canvas sidebar
     *
     * @param {?Array} cookies
     * @private
     */
    _setInitialState(cookies = null) {
        const availableCookies = cookies || this._getCookies('all');
        const activeCookies = [];
        const inactiveCookies = [];

        availableCookies.forEach(({ cookie, required }) => {
            const isActive = CookieStorage.getItem(cookie);
            if (isActive || required) {
                activeCookies.push(cookie);
            } else {
                inactiveCookies.push(cookie);
            }
        });

        this.lastState = {
            active: activeCookies,
            inactive: inactiveCookies,
        };
    }

    /**
     * Preselect coherent checkboxes in the off canvas sidebar
     *
     * @private
     */
    _setInitialOffcanvasState() {
        const activeCookies = this.lastState.active;
        const offCanvas = this._getOffCanvas();

        activeCookies.forEach(activeCookie => {
            const target = offCanvas.querySelector(`[data-cookie="${activeCookie}"]`);

            target.checked = true;
            this._childCheckboxEvent(target);
        });
    }

    /**
     * From click target, try to find the cookie group container and toggle the open state
     *
     * @param event
     * @private
     */
    _handleWrapperTrigger(event) {
        event.preventDefault();
        const { entriesActiveClass, entriesClass, groupClass } = this.options;
        const { target } = event;

        const cookieEntryContainer = this._findParentEl(target, entriesClass, groupClass);

        if (cookieEntryContainer) {
            const active = cookieEntryContainer.classList.contains(entriesActiveClass);

            if (active) {
                cookieEntryContainer.classList.remove(entriesActiveClass);
            } else {
                cookieEntryContainer.classList.add(entriesActiveClass);
            }
        }
    }

    /**
     * Determine whether the target checkbox is a parent or a child checkbox
     *
     * @param event
     * @private
     */
    _handleCheckbox(event) {
        const { parentInputClass } = this.options;
        const { target } = event;

        const callback = target.classList.contains(parentInputClass) ? this._parentCheckboxEvent : this._childCheckboxEvent;

        callback.call(this, target);
    }


    /**
     * Recursively checks the provided elements parent for the first class parameter
     * Stops the recursion, if the parentElement contains the second class parameter
     *
     * @param el
     * @param findClass
     * @param abortClass
     * @returns {*|HTMLElement|*}
     * @private
     */
    _findParentEl(el, findClass, abortClass = null) {
        while (!!el && !el.classList.contains(abortClass)) {
            if (el.classList.contains(findClass)) {
                return el;
            }
            el = el.parentElement;
        }

        return null;
    }

    _isChecked(target) {
        return !!target.checked;
    }

    /**
     * De-/select all checkboxes of the current group
     *
     * @param target
     * @private
     */
    _parentCheckboxEvent(target) {
        const { groupClass } = this.options;
        const newState = this._isChecked(target);
        const group = this._findParentEl(target, groupClass);

        this._toggleWholeGroup(newState, group);
    }

    /**
     *
     * Trigger a change event for the "select-all" checkbox of the childs group
     *
     * @param target
     * @private
     */
    _childCheckboxEvent(target) {
        const { groupClass } = this.options;
        const newState = this._isChecked(target);
        const group = this._findParentEl(target, groupClass);

        this._toggleParentCheckbox(newState, group);
    }

    /**
     * Toogle each checkbox inside the given group
     *
     * @param state
     * @param group
     * @private
     */
    _toggleWholeGroup(state, group) {
        Array.from(group.querySelectorAll('input')).forEach(checkbox => {
            checkbox.checked = state;
        });
    }

    /**
     * Toggle a groups "select-all" checkbox according to changes to its child checkboxes
     * "Check, if any child checkbox is checked" / "Uncheck, if no child checkboxes are checked"
     *
     * @param state
     * @param group
     * @private
     */
    _toggleParentCheckbox(state, group) {
        const { parentInputSelector } = this.options;
        const checkboxes = Array.from(group.querySelectorAll(`input:not(${parentInputSelector})`));
        const activeCheckboxes = Array.from(group.querySelectorAll(`input:not(${parentInputSelector}):checked`));

        if (checkboxes.length > 0) {
            const parentCheckbox = group.querySelector(parentInputSelector);

            if (parentCheckbox) {
                const checked = activeCheckboxes.length > 0;
                const indeterminate = checked && activeCheckboxes.length !== checkboxes.length;

                parentCheckbox.checked = checked;
                parentCheckbox.indeterminate = indeterminate;
            }
        }
    }

    /**
     * Event handler for the 'Save' button inside the offCanvas
     *
     * Removes unselected cookies, if already set
     * Sets or refreshes selected cookies
     *
     * @private
     */
    _handleSubmit() {
        const activeCookies = this._getCookies('active');
        const inactiveCookies = this._getCookies('inactive');
        const { cookiePreference } = this.options;

        const activeCookieNames = [];
        const inactiveCookieNames = [];

        inactiveCookies.forEach(({ cookie }) => {
            inactiveCookieNames.push(cookie);

            if (CookieStorage.getItem(cookie)) {
                CookieStorage.removeItem(cookie);
            }
        });

        /**
         * Cookies without value are passed to the updateListener
         * ( see "_handleUpdateListener" method )
         */
        activeCookies.forEach(({ cookie, value, expiration }) => {
            activeCookieNames.push(cookie);

            if (cookie && value) {
                CookieStorage.setItem(cookie, value, expiration);
            }
        });

        CookieStorage.setItem(cookiePreference, '1', '30');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this.closeOffCanvas(document.$emitter.publish(COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS));
    }

    /**
     * Accepts all cookies. Pass `true` to the loadIntoMemory parameter to load the DOM into memory instead of
     * opening the OffCanvas menu.
     *
     * @param loadIntoMemory
     */
    acceptAllCookies(loadIntoMemory = false) {
        if (!loadIntoMemory) {
            this._handleAcceptAll();
            this.closeOffCanvas();

            return;
        }

        ElementLoadingIndicatorUtil.create(this.el);

        const url = window.router['frontend.cookie.offcanvas'];

        this._httpClient.get(url, (response) => {
            const dom = new DOMParser().parseFromString(response, 'text/html');

            this._handleAcceptAll(dom);

            ElementLoadingIndicatorUtil.remove(this.el);
            this._hideCookieBar();
        });
    }

    /**
     * Event handler for the 'Allow all'-button in the cookie bar.
     * It loads the DOM into memory before searching for, and accepting the cookies.
     *
     * @private
     */
    _acceptAllCookiesFromCookieBar() {
        return this.acceptAllCookies(true);
    }

    /**
     * Event handler for the 'Allow all'-button in the off canvas view.
     * It uses the DOM from the Off Canvas container to search for, and accept the cookies.
     * After accepting, it closes the OffCanvas sidebar.
     *
     * @private
     */
    _acceptAllCookiesFromOffCanvas() {
        return this.acceptAllCookies();
    }

    /**
     * This will set and refresh all registered cookies.
     *
     * @param {?(Document|HTMLElement)} offCanvas
     * @private
     */
    _handleAcceptAll(offCanvas = null) {
        const allCookies = this._getCookies('all', offCanvas);
        this._setInitialState(allCookies);
        const { cookiePreference } = this.options;

        allCookies.forEach(({ cookie, value, expiration }) => {
            if (cookie && value) {
                CookieStorage.setItem(cookie, value, expiration);
            }
        });

        CookieStorage.setItem(cookiePreference, '1', '30');

        this._handleUpdateListener(allCookies.map(({ cookie }) => cookie), []);
    }

    /**
     * Get cookies passed to the configuration template
     * Can be filtered by "all", "active" or "inactive"
     *
     * Always excludes "required" cookies, since they are assumed to be set separately.
     *
     * @param type
     * @param {?(Document|HTMLElement)} offCanvas
     * @returns {Array}
     * @private
     */
    _getCookies(type = 'all', offCanvas = null) {
        const { cookieSelector } = this.options;
        if (!offCanvas) {
            offCanvas = this._getOffCanvas();
        }

        return Array.from(offCanvas.querySelectorAll(cookieSelector)).filter(cookieInput => {
            switch (type) {
                case 'all': return true;
                case 'active': return this._isChecked(cookieInput);
                case 'inactive': return !this._isChecked(cookieInput);
                default: return false;
            }
        }).map(filteredInput => {
            const { cookie, cookieValue, cookieExpiration, cookieRequired } = filteredInput.dataset;
            return { cookie, value: cookieValue, expiration: cookieExpiration, required: cookieRequired };
        });
    }

    /**
     * Returns the current offcanvas element if available
     *
     * @returns {*}
     * @private
     */
    _getOffCanvas() {
        const elements = OffCanvas ? OffCanvas.getOffCanvas() : [];

        return (elements && elements.length > 0) ? elements[0] : false;
    }
}
