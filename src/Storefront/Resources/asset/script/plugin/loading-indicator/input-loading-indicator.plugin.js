import LoadingIndicator from "./loading-indicator.plugin";

export default class InputLoadingIndicator extends LoadingIndicator {

    /**
     * Constructor
     * @param {Element|string} parent
     * @param position
     */
    constructor(parent, position = "before") {
        super(parent, position);

        if (this._isInputElement() === false) {
            throw Error('Parent element is not of type <input>');
        }
    }

    /**
     * Insert the loading indicator after the input field
     */
    create() {
        if (this.exists()) return;
        this.parent.insertAdjacentHTML('afterend', LoadingIndicator.getTemplate());
    }

    /**
     * remove loading indicators
     */
    remove() {
        let indicators = this.parent.parentNode.querySelectorAll(`.${LoadingIndicator.SELECTOR_CLASS()}`);
        indicators.forEach(indicator => indicator.remove());
    }

    /**
     * Verify whether the injected parent is of type <input> or not
     * @returns {boolean}
     * @private
     */
    _isInputElement() {
        return (this.parent.tagName.toLowerCase() === "input");
    }
}