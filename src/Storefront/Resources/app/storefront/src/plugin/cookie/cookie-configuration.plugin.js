/**
 *
 * CookieConfiguration plugin
 * --------------------------
 * Manages cookie consent configuration with three modes:
 * - 'required': Only technically required cookies
 * - 'all': Accept all available cookies
 * - 'selected': User-selected cookies from the offCanvas form
 *
 * API-driven approach:
 * Uses the `/store-api/cookie-groups` endpoint (see CookieRoute.php) to fetch
 * cookie configuration including cookie-config-hash and cookie-preference values.
 * The endpoint provides both cookie metadata and values, ensuring consistency
 * between backend configuration and frontend cookie handling.
 *
 * Hash-based configuration tracking:
 * Automatically resets to required cookies when cookie-config-hash changes,
 * prompting users to review updated cookie settings.
 *
 * Rendering:
 * Renders the configuration template inside an offCanvas via CookieController.php
 *
 * Event handlers:
 * Applies its "openOffCanvas"-eventHandler to:
 * 1) '.js-cookie-configuration-button button'
 * 2) `[href="${window.router['frontend.cookie.offcanvas']}"]`
 * Can be opened manually using the public method "openOffCanvas"
 *
 * Events:
 * Configuration changes are pushed to the global event "CookieConfiguration_Update"
 *
 * @sw-package framework
 */

/* global PluginManager */

import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';
import OffCanvas, { OffCanvasInstance } from 'src/plugin/offcanvas/offcanvas.plugin';
import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

// These events will be published via a global (document) EventEmitter
export const COOKIE_CONFIGURATION_UPDATE = 'CookieConfiguration_Update';
export const COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS = 'CookieConfiguration_CloseOffCanvas';

export default class CookieConfiguration extends Plugin {

    static lastTriggerElement = null;

    static options = {
        defaultCookieExpiration: 30,
        offCanvasPosition: 'left',
        submitEvent: 'click',
        cookiePreference: 'cookie-preference',
        cookieConfigHash: 'cookie-config-hash',
        cookieSelector: '[data-cookie]',
        buttonOpenSelector: '.js-cookie-configuration-button button',
        buttonSubmitSelector: '.js-offcanvas-cookie-submit',
        buttonPermissionSelector: '.js-cookie-permission-button',
        buttonAcceptAllSelector: '.js-offcanvas-cookie-accept-all',
        globalButtonAcceptAllSelector: '.js-cookie-accept-all-button',
        globalButtonPermissionSelector: '.js-cookie-permission-button',
        parentInputSelector: '.offcanvas-cookie-parent-input',
        customLinkSelector: `[href="${window.router['frontend.cookie.offcanvas']}"]`,
        entriesActiveClass: 'offcanvas-cookie-entries--active',
        entriesClass: 'offcanvas-cookie-entries',
        groupClass: 'offcanvas-cookie-group',
        parentInputClass: 'offcanvas-cookie-parent-input',
        // Consent offcanvas selectors
        consentAcceptButtonSelector: '.js-wishlist-cookie-accept',
        consentLoginButtonSelector: '.js-wishlist-login',
        consentCancelButtonSelector: '.js-wishlist-cookie-offcanvas-cancel',
        consentPreferencesButtonSelector: '.js-wishlist-cookie-preferences',
    };

    init() {
        this.lastState = {
            active: [],
            inactive: [],
        };
        this._cleanupTasks = [];

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this._httpClient = new HttpClient();

        this._registerEvents();
        this._checkCookieConfigurationHash();

        document.$emitter.subscribe('CookieConfiguration/requestConsent', (payload) => {
            if (payload instanceof CustomEvent) {
                payload = payload.detail;
            }
            this.openRequestConsentOffCanvas(payload.route, payload.cookieName);
        });
        this._registerCleanup(() => {
            document.$emitter.unsubscribe('CookieConfiguration/requestConsent');
        });
    }

    /**
     * Clean up event listeners and resources when plugin is destroyed
     */
    destroy() {
        if (this._cleanupTasks && this._cleanupTasks.length > 0) {
            const tasks = this._cleanupTasks.slice();
            this._cleanupTasks = [];
            for (let i = 0; i < tasks.length; i++) {
                try {
                    tasks[i]();
                } catch (_error) {
                    // noop: best-effort cleanup
                }
            }
        }
    }

    /**
     * Get the default cookie expiration value with validation
     * Ensures the value is a number and falls back to 30 if invalid
     * @returns {number}
     * @private
     */
    _getDefaultCookieExpiration() {
        const { defaultCookieExpiration } = this.options;
        const parsed = Number(defaultCookieExpiration);

        return (Number.isInteger(parsed) && parsed > 0) ? parsed : 30;
    }

    /**
     * Registers the events for displaying the offCanvas
     * Applies the event to all elements using the "buttonOpenSelector" or "customLinkSelector"
     * Uses event delegation to handle dynamically loaded content
     *
     * @private
     */
    _registerEvents() {
        // Prevent duplicate event handler registration
        if (this._delegatedEventHandler) {
            return;
        }

        const { submitEvent, buttonOpenSelector, customLinkSelector, buttonPermissionSelector, globalButtonAcceptAllSelector } = this.options;

        // Store the handler reference for cleanup
        this._delegatedEventHandler = (event) => {
            const target = event.target;

            // Check for custom link (e.g., cookie offcanvas link)
            const customLink = target.closest(customLinkSelector);
            if (customLink) {
                this._handleCustomLink(event);
                return;
            }

            // Check for button open selector
            const button = target.closest(buttonOpenSelector);
            if (button) {
                this.openOffCanvas();
                return;
            }

            // Check for permission button
            const buttonPermission = target.closest(buttonPermissionSelector);
            if (buttonPermission) {
                this._handlePermission(event);
                return;
            }

            // Check for accept all button
            const acceptAllButton = target.closest(globalButtonAcceptAllSelector);
            if (acceptAllButton) {
                this._acceptAllCookiesFromCookieBar(event);
                return;
            }
        };

        // Use single event delegation handler to avoid multiple listeners
        // Use capture phase to ensure this runs before other click handlers
        document.addEventListener(submitEvent, this._delegatedEventHandler, true);
        this._registerCleanup(() => {
            document.removeEventListener(submitEvent, this._delegatedEventHandler, true);
            this._delegatedEventHandler = null;
        });
    }

    /**
     * Registers events required by the offCanvas template using event delegation
     * This prevents duplicate event listeners and memory leaks
     *
     * @private
     */
    _registerOffCanvasEvents() {
        const offCanvas = this._getOffCanvas();

        if (offCanvas && !offCanvas.hasAttribute('data-offcanvas-events-registered')) {
            // Create and store a stable handler reference for later removal
            if (!this._offCanvasDelegatedHandler) {
                this._offCanvasDelegatedHandler = this._handleOffCanvasClick.bind(this);
            }

            // Mark as registered to prevent duplicate registration
            offCanvas.setAttribute('data-offcanvas-events-registered', 'true');

            // Attach the delegated event listener
            offCanvas.addEventListener('click', this._offCanvasDelegatedHandler, true);
            this._registerCleanup(() => {
                offCanvas.removeEventListener('click', this._offCanvasDelegatedHandler, true);
                offCanvas.removeAttribute('data-offcanvas-events-registered');
                this._offCanvasDelegatedHandler = null;
            });
        }
    }

    /**
     * Handles all click events within the offcanvas using event delegation
     * This prevents memory leaks and duplicate event listeners
     *
     * @param {Event} event
     * @private
     */
    _handleOffCanvasClick(event) {
        const { buttonSubmitSelector, buttonAcceptAllSelector } = this.options;
        const target = event.target;

        // Handle submit button
        const submitButton = target.closest(buttonSubmitSelector);
        if (submitButton) {
            event.preventDefault();
            this._handleSubmit(event);
            return;
        }

        // Handle accept all button
        const acceptAllButton = target.closest(buttonAcceptAllSelector);
        if (acceptAllButton) {
            event.preventDefault();
            this._acceptAllCookiesFromOffCanvas(event);
            return;
        }

        // Handle checkboxes
        if (target.type === 'checkbox') {
            this._handleCheckbox(event);
            return;
        }
    }

    /**
     * Handle custom link clicks (e.g., cookie offcanvas links)
     * Prevents default behavior only for normal left-clicks, allows middle-click and Ctrl/Cmd+click for new tabs
     *
     * @param event
     * @private
     */
    _handleCustomLink(event) {
        // Allow middle-click, right-click, and Ctrl/Cmd+click for normal browser behavior
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        // Check if event was already prevented by another handler
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        this.openOffCanvas();
    }

    /**
     * Fetch cookie groups from the server
     * @private
     * @returns {Promise<Object|null>} Cookie groups data with hash and elements, or null if error
     */
    async _fetchCookieGroups() {
        try {
            const url = window.router['frontend.cookie.groups'];
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            return await response.json();
        } catch (error) {
            console.error('Failed to fetch cookie groups:', error);
            return null;
        }
    }

    /**
     * Check if cookie configuration hash has changed and reset cookies if needed
     * @private
     */
    async _checkCookieConfigurationHash() {
        const { cookiePreference, cookieConfigHash } = this.options;
        const hasPreference = CookieStorage.getItem(cookiePreference);
        const storedHash = CookieStorage.getItem(cookieConfigHash);

        if (!hasPreference && !storedHash) {
            return;
        }

        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const currentHash = data.hash;

        if (storedHash && storedHash !== currentHash) {
            await this._resetCookieConfiguration(data);
        }

        CookieStorage.setItem(cookieConfigHash, currentHash, this._getDefaultCookieExpiration());
    }

    /**
     * Reset cookie configuration when hash has changed
     * Resets to technically required cookies only
     * @private
     */
    async _resetCookieConfiguration(data) {
        const cookieGroups = data.elements || [];
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(cookieGroups, 'required');

        CookieStorage.removeItem(this.options.cookiePreference);

        const updatedActiveCookieNames = activeCookieNames.filter(name => name !== this.options.cookiePreference);
        this._handleUpdateListener(updatedActiveCookieNames, inactiveCookieNames);

        this._checkAndShowCookieBarIfNeeded();
    }

    /**
     * Extract all cookie names from cookie groups
     * @private
     */
    _getAllCookieNamesFromGroups(cookieGroups) {
        return cookieGroups
            .flatMap(group => group.entries ? group.entries.map(entry => entry.cookie) : (group.cookie ? [group.cookie] : []))
            .filter(cookieName => cookieName);
    }

    /**
     * Get technically required cookie names that are managed by PHP
     * These cookies should not be set by JavaScript
     * @private
     */
    _getTechnicallyRequiredCookieNames() {
        return ['session-', 'timezone'];
    }


    async _handlePermission(event) {
        event.preventDefault();

        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const cookieGroups = data.elements;
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(cookieGroups, 'required');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);

        this._hideCookieBar();
        this.closeOffCanvas();
    }

    /**
     * Unified cookie application method for all three cases:
     * 1. Technical required only
     * 2. Accept all
     * 3. Selected cookies (from DOM checkboxes)
     *
     * @param {Array} cookieGroups - Array of cookie groups from API
     * @param {string} mode - 'required' | 'all' | 'selected'
     * @param {Array} selectedCookies - Array of selected cookie names (only for mode='selected')
     * @returns {{activeCookieNames: Array, inactiveCookieNames: Array}}
     * @private
     */
    _applyCookieConfiguration(cookieGroups, mode = 'all', selectedCookies = []) {
        const phpManagedCookies = this._getTechnicallyRequiredCookieNames();
        const allCookiesFromApi = this._extractAllCookiesFromGroups(cookieGroups);
        const cookiesToSet = [];
        const cookiesToRemove = [];

        for (let i = 0; i < allCookiesFromApi.length; i++) {
            const cookieData = allCookiesFromApi[i];
            const shouldBeActive = mode === 'required' ? cookieData.isRequired
                : mode === 'selected' ? (cookieData.isRequired || selectedCookies.includes(cookieData.cookie))
                    : true;

            if (shouldBeActive) {
                cookiesToSet.push(cookieData);
            } else {
                cookiesToRemove.push(cookieData.cookie);
            }
        }

        for (let i = 0; i < cookiesToRemove.length; i++) {
            if (CookieStorage.getItem(cookiesToRemove[i])) {
                CookieStorage.removeItem(cookiesToRemove[i]);
            }
        }

        const activeCookieNames = [...phpManagedCookies];
        for (let i = 0; i < cookiesToSet.length; i++) {
            const cookieData = cookiesToSet[i];
            const isPhpManaged = phpManagedCookies.some(phpCookie => cookieData.cookie === phpCookie);

            if (!isPhpManaged) {
                activeCookieNames.push(cookieData.cookie);
            }

            if (cookieData.value && !isPhpManaged) {
                CookieStorage.setItem(
                    cookieData.cookie,
                    cookieData.value,
                    cookieData.expiration || this._getDefaultCookieExpiration()
                );
            }
        }

        return {
            activeCookieNames,
            inactiveCookieNames: cookiesToRemove,
        };
    }

    /**
     * Extract all cookies from cookie groups (both entries and direct group cookies)
     * @param {Array} cookieGroups - Array of cookie groups from API
     * @returns {Array} Array of cookie data objects with {cookie, value, expiration, isRequired}
     * @private
     */
    _extractAllCookiesFromGroups(cookieGroups) {
        const cookies = [];

        for (let i = 0; i < cookieGroups.length; i++) {
            const group = cookieGroups[i];
            const isRequired = group.isRequired || false;

            if (group.entries) {
                for (let j = 0; j < group.entries.length; j++) {
                    const entry = group.entries[j];
                    if (entry.cookie) {
                        cookies.push({
                            cookie: entry.cookie,
                            value: entry.value,
                            expiration: entry.expiration,
                            isRequired,
                        });
                    }
                }
            }

            if (group.cookie) {
                cookies.push({
                    cookie: group.cookie,
                    value: group.value,
                    expiration: group.expiration,
                    isRequired,
                });
            }
        }

        return cookies;
    }

    _handleUpdateListener(active, inactive) {
        const updatedCookies = this._getUpdatedCookies(active, inactive);

        if (typeof window.registerGoogleReCaptchaPlugins === 'function') {
            window.registerGoogleReCaptchaPlugins();
            PluginManager.initializePlugins();
        }

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
    openOffCanvas(callback = null) {
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
     * Check if cookie preference is set and show cookie bar if needed
     * @private
     */
    _checkAndShowCookieBarIfNeeded() {
        const { cookiePreference } = this.options;
        const cookiePermission = CookieStorage.getItem(cookiePreference);

        if (!cookiePermission) {
            const showCookieBarEvent = new CustomEvent('showCookieBar');
            document.dispatchEvent(showCookieBarEvent);
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
        this._registerOffCanvasCloseListener();
        PluginManager.initializePlugins();

        if (typeof callback === 'function') {
            callback();
        }
    }

    /**
     * Register listener for offcanvas close events
     * @private
     */
    _registerOffCanvasCloseListener() {
        // Subscribe once to the OffCanvas close event with a namespaced event
        this._onOffCanvasOnceHandler = () => {
            this._checkAndShowCookieBarIfNeeded();
        };

        OffCanvasInstance.$emitter.subscribe('onCloseOffcanvas', this._onOffCanvasOnceHandler, { once: true });
        this._registerCleanup(() => {
            OffCanvasInstance.$emitter.unsubscribe('onCloseOffcanvas');
            this._onOffCanvasOnceHandler = null;
        });
    }

    /**
     * Register a cleanup callback to be executed on destroy
     * @param {Function} cleanupFn
     * @private
     */
    _registerCleanup(cleanupFn) {
        if (!this._cleanupTasks) {
            this._cleanupTasks = [];
        }
        this._cleanupTasks.push(cleanupFn);
    }

    _hideCookieBar() {
        const hideCookieBarEvent = new CustomEvent('hideCookieBar');
        document.dispatchEvent(hideCookieBarEvent);
    }

    /**
     * Opens a feature-specific consent offcanvas
     *
     * @param {string} route
     * @param {string} cookieName
     */
    openRequestConsentOffCanvas(route, cookieName) {
        if (!route || !cookieName) {
            return;
        }

        CookieConfiguration.lastTriggerElement = document.activeElement;

        AjaxOffCanvas.open(route, false, () => {
            window.PluginManager.initializePlugins();
            const offcanvas = document.querySelector('.offcanvas');
            if (!offcanvas){
                return;
            }
            this._registerConsentOffcanvasEvents(offcanvas, cookieName);
        }, 'left');
    }

    /**
     * Register event listeners for the consent offcanvas
     *
     * @param {HTMLElement} offcanvas
     * @param {string} cookieName
     */
    _registerConsentOffcanvasEvents(offcanvas, cookieName) {
        const {
            consentAcceptButtonSelector,
            consentLoginButtonSelector,
            consentCancelButtonSelector,
            consentPreferencesButtonSelector,
        } = this.options;

        const acceptBtn = offcanvas.querySelector(consentAcceptButtonSelector);
        if (acceptBtn) {
            acceptBtn.addEventListener('click', this._onAccept.bind(this, cookieName));
        }

        const loginBtn = offcanvas.querySelector(consentLoginButtonSelector);
        if (loginBtn) {
            loginBtn.addEventListener('click', this._onLogin.bind(this));
        }

        const cancelBtn = offcanvas.querySelector(consentCancelButtonSelector);
        if (cancelBtn) {
            cancelBtn.addEventListener('click', this._onCancel.bind(this));
        }

        const prefBtn = offcanvas.querySelector(consentPreferencesButtonSelector);
        if (prefBtn) {
            prefBtn.addEventListener('click', this._onPreferences.bind(this));
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
    _toggleParentCheckbox(_state, group) {
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
     * Uses the API endpoint to get cookie configuration and applies selected cookies from DOM
     *
     * @private
     */
    async _handleSubmit() {
        const selectedCookiesFromDOM = this._getCookies('active')
            .filter(({ required }) => !required)
            .map(({ cookie }) => cookie);

        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const cookieGroups = data.elements;
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(
            cookieGroups,
            'selected',
            selectedCookiesFromDOM
        );

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this.closeOffCanvas(document.$emitter.publish(COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS));
    }

    /**
     * Public method to accept all cookies.
     * Uses the API endpoint to fetch and accept all cookies.
     *
     * @param {boolean} _loadIntoMemory - Deprecated parameter, kept for backward compatibility
     * @deprecated tag:v6.8.0 - The _loadIntoMemory parameter is deprecated and has no effect
     */
    // eslint-disable-next-line no-unused-vars
    async acceptAllCookies(_loadIntoMemory = false) {
        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const cookieGroups = data.elements;
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(cookieGroups, 'all');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this._hideCookieBar();
        this.closeOffCanvas();
    }

    /**
     * Event handler for the 'Allow all'-button in the cookie bar.
     * Uses the API endpoint to fetch and accept all cookies.
     *
     * @private
     */
    async _acceptAllCookiesFromCookieBar() {
        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const cookieGroups = data.elements;
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(cookieGroups, 'all');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this._hideCookieBar();
    }

    /**
     * Event handler for the 'Allow all'-button in the off canvas view.
     * Uses the API endpoint to fetch and accept all cookies, then closes the offcanvas.
     *
     * @private
     */
    async _acceptAllCookiesFromOffCanvas() {
        const data = await this._fetchCookieGroups();
        if (!data) {
            return;
        }

        const cookieGroups = data.elements;
        const { activeCookieNames, inactiveCookieNames } = this._applyCookieConfiguration(cookieGroups, 'all');

        this._handleUpdateListener(activeCookieNames, inactiveCookieNames);
        this.closeOffCanvas(document.$emitter.publish(COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS));
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

    /**
     * @private
     * @param {string} cookieName
     */
    _onAccept(cookieName) {
        CookieStorage.setItem(cookieName, '1', this._getDefaultCookieExpiration());
        AjaxOffCanvas.close();
    }

    /**
     * @private
     */
    _onLogin() {
        AjaxOffCanvas.close();
        window.location.href = window.router['frontend.account.login.page'];
    }

    /**
     * @private
     */
    _onCancel() {
        AjaxOffCanvas.close();
    }

    /**
     * @private
     */
    _onPreferences(e) {
        e.preventDefault();
        AjaxOffCanvas.close();
        this.openOffCanvas(() => {
            const offcanvasElement = document.querySelector('.offcanvas');
            if (!offcanvasElement) {
                return;
            }
            offcanvasElement.addEventListener('hidden.bs.offcanvas',
                this._restoreFocus.bind(this),
                { once: true }
            );
        });
    }

    /**
     * Restores focus to the element that triggered the consent offcanvas (e.g., add-to-wishlist button)
     * @private
     */
    _restoreFocus() {
        const btn = CookieConfiguration.lastTriggerElement;
        btn?.focus?.();
    }
}
