/**
 * This class is used to make it easier to preserve the focus state.
 * It is used to set the focus back to a given element after displaying content in a modal.
 * @package storefront
 * @namespace window
 * @module FocusHandler
 * @example
 * // 1. A button <button class="launch-overlay-el"> is clicked and gets focused.
 * window.focusHandler.saveFocusState('custom-overlay', document.querySelector('.launch-overlay-el'));
 * // 2. An overlay opens that moved away the focus from the previously clicked button.
 * // 3. After the overlay is closed, the focus can be resumed on the previously clicked element.
 * window.focusHandler.resumeFocusState('custom-overlay');
 */
export default class FocusHandler {

    constructor(defaultHistoryKey = 'lastFocus', defaultStorageKeyPrefix = 'sw-last-focus') {

        // The key under which the focus state is saved by default.
        this._defaultHistoryKey = defaultHistoryKey;

        // The prefix for the session storage key.
        this._defaultStorageKeyPrefix = defaultStorageKeyPrefix;

        // Stores different focus states.
        this._focusMap = new Map();
    }

    /**
     * Saves the current focus state under the given key.
     * If not explicitly set, the element that is currently in focus will be saved.
     * It is also possible to pass a selector (string) to search for a specific element during `resumeFocusState`.
     * This can be used when the original element reference is no longer available. E.g. due to DOM modifications.
     *
     * @param focusHistoryKey {string} - The key under which the focus should be saved in memory.
     * @param focusEl {HTMLElement|string} - The element of which the focus should be saved. Can be an HTMLElement of selector string.
     * @public
     */
    saveFocusState(focusHistoryKey = this._defaultHistoryKey, focusEl = document.activeElement) {
        this._focusMap.set(focusHistoryKey, focusEl);

        document.$emitter.publish('Focus/StateSaved', {
            focusHistoryKey,
            focusEl,
        });
    }

    /**
     * Resumes the focus to the element that was saved for the given key.
     *
     * @param focusHistoryKey {string} - The key under which the focus should be resumed.
     * @param focusOptions
     * @param focusOptions.preventScroll {boolean} - Should prevent the scroll
     * @param focusOptions.focusVisible {boolean} - Visible focus
     * @public
     */
    resumeFocusState(focusHistoryKey = this._defaultHistoryKey, focusOptions = {}) {
        let focusEl = this._focusMap.get(focusHistoryKey);

        // If the `focusEl` is a string, we assume it is a selector and query the element first.
        if (typeof focusEl === 'string') {
            focusEl = document.querySelector(focusEl);
        }

        this.setFocus(focusEl, focusOptions);

        document.$emitter.publish('Focus/StateResumed', {
            focusHistoryKey,
            focusEl,
        });
    }

    /**
     * Saves the current focus state under the given key in the session storage.
     * By default, the given key will be prefixed with the `defaultStorageKeyPrefix` "sw-last-focus".
     * A unique selector is mandatory to resume the focus state on the correct element. (e.g. after a page reload)
     *
     * @param focusStorageKey
     * @param uniqueSelector
     * @public
     */
    saveFocusStatePersistent(focusStorageKey, uniqueSelector) {
        if (!uniqueSelector || !focusStorageKey) {
            console.error('[FocusHandler]: Unable to save focus state. Parameters "focusStorageKey" and "uniqueSelector" are required.');
            return;
        }

        // Default sessionStorage structure:
        // key: "sw-last-focus-my-example-element" | value: "#my-example-unique-id"
        try {
            const storageKey = `${this._defaultStorageKeyPrefix}-${focusStorageKey}`;
            window.sessionStorage.setItem(storageKey, uniqueSelector);

            document.$emitter.publish('Focus/StateSavedPersistent', {
                focusStorageKey,
                uniqueSelector,
            });
        } catch (e) {
            console.warn('[FocusHandler] Unable to access sessionStorage', e);
        }
    }

    /**
     * Resumes the focus to the element that was saved for the given key in the session storage.
     *
     * @param focusStorageKey
     * @param focusOptions
     * @public
     */
    resumeFocusStatePersistent(focusStorageKey, focusOptions) {
        try {
            const uniqueSelector = window.sessionStorage.getItem(`${this._defaultStorageKeyPrefix}-${focusStorageKey}`);
            if (!uniqueSelector) {
                return;
            }

            const focusEl = document.querySelector(uniqueSelector);
            this.setFocus(focusEl, focusOptions);
            window.sessionStorage.removeItem(`${this._defaultStorageKeyPrefix}-${focusStorageKey}`);

            document.$emitter.publish('Focus/StateResumedPersistent', {
                focusStorageKey,
                focusEl,
            });
        } catch (e) {
            console.warn('[FocusHandler] Unable to access sessionStorage', e);
        }
    }

    /**
     * Tries to set the focus to the given element.
     *
     * @param {HTMLElement} el
     * @param {{preventScroll: boolean, focusVisible: boolean}} focusOptions
     * @public
     */
    setFocus(el, focusOptions) {
        if (!el) {
            return;
        }
        try {
            el.focus(focusOptions);
        } catch (error) {
            console.error('[FocusHandler]: Unable to focus element.', error);
        }
    }
}
