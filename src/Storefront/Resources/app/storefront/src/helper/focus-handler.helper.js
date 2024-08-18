/**
 * This class is used to make it easier to preserve the focus state.
 * It is used to set the focus back to a given element after displaying content in a modal.
 *
 * @package storefront
 */
export default class FocusHandler {

    constructor(defaultHistoryKey = 'lastFocus') {

        // The key under which the focus state is saved by default.
        this._defaultHistoryKey = defaultHistoryKey;

        // Stores different focus states.
        this._focusMap = new Map();
    }

    /**
     * Saves the current focus state under the given key.
     * If not explicitly set, the element that is currently in focus will be saved.
     * It is also possible to pass a selector (string) to search for a specific element during `resumeFocusState`.
     * This can be used when the original element reference is no longer available. E.g. due to DOM modifications.
     *
     * @param {string} focusHistoryKey
     * @param {HTMLElement|string} focusEl
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
     * @param {string} focusHistoryKey
     * @param {{preventScroll: boolean, focusVisible: boolean}} focusOptions
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
     * Tries to set the focus to the given element.
     *
     * @param {HTMLElement} el
     * @param {{preventScroll: boolean, focusVisible: boolean}} focusOptions
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
