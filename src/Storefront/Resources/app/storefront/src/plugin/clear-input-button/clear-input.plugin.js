import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';

/**
 * Adds clear functionality to input fields
 *
 * @class
 */
export default class ClearInputPlugin extends Plugin {
    static options = {
        /**
         * Optional selector for a custom clear button
         *
         * @type string
         */
        clearButtonSelector: '',
    };

    init() {
        this.clearButtons = DomAccess.querySelectorAll(
            document,
            this.options.clearButtonSelector
        );

        this.onInputChange();
        this._registerEventListener();
    }

    /**
     * Registers all event listeners
     *
     * @returns {void}
     */
    _registerEventListener() {
        this.clearButtons.forEach((clearButton) => {
            clearButton.addEventListener('click', this.clearInput.bind(this));
        });
        this.el.addEventListener('input', this.onInputChange.bind(this));
    }

    /**
     * Clears the input field
     *
     * @returns {void}
     */
    clearInput() {
        this.el.value = '';

        const event = document.createEvent('HTMLEvents');
        event.initEvent('change', true, false);

        this.el.dispatchEvent(event);
        this.onInputChange();
    }

    /**
     * Sets the button to disabled if the input is empty and vice versa
     *
     * @returns {void}
     */
    onInputChange() {
        this.clearButtons.forEach((clearButton) => {
            if (this.el.value.length <= 0) {
                clearButton.setAttribute('disabled', 'disabled');
                return;
            }
            clearButton.removeAttribute('disabled');
        });
    }
}
