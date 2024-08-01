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
     *
     * @param {string} focusHistoryKey
     * @param {HTMLElement} focusEl
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
     * @param focusHistoryKey
     */
    resumeFocusState(focusHistoryKey = this._defaultHistoryKey) {
        const focusEl = this._focusMap.get(focusHistoryKey);

        this.setFocus(focusEl);

        document.$emitter.publish('Focus/StateResumed', {
            focusHistoryKey,
            focusEl,
        });
    }

    /**
     * Tries to set the focus to the given element.
     *
     * @param {HTMLElement} el
     */
    setFocus(el) {
        try {
            el.focus();
        } catch (error) {
            console.error(`[FocusHandler]: Unable to focus element ${el.tagName}.`, error);
        }
    }
}
