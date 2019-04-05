import Plugin from 'asset/script/helper/plugin/plugin.class';
import DomAccess from 'asset/script/helper/dom-access.helper';

// TODO: NEXT-2714 - use input-validation helper

const DIFFERENT_SHIPPING_SHOW_CLASS = 'js-show';
const DIFFERENT_SHIPPING_CONTAINER_CLASS = 'js-different-shipping-container';
const DIFFERENT_SHIPPING_REQUIRED_CLASS = 'js-required';
const DIFFERENT_SHIPPING_CHECKBOX_CLASS = 'js-different-shipping-checkbox';

export default class DifferentShipping extends Plugin {

    init() {
        try {
            this.checkbox = DomAccess.querySelector(document, `.${DIFFERENT_SHIPPING_CHECKBOX_CLASS}`);
            this.container = DomAccess.querySelector(document, `.${DIFFERENT_SHIPPING_CONTAINER_CLASS}`);
        } catch (e) {
            return;
        }

        this._registerEvents();

        this._setVisibility(this.checkbox.checked);
        this._setFieldState(this.checkbox.checked);
    }

    /**
     * Registers all needed events
     *
     * @private
     */
    _registerEvents() {
        this.checkbox.addEventListener('change', this._onCheckboxChange.bind(this));
    }

    /**
     * Performs actions on checkbox change event
     * @param {Event} e
     * @private
     */
    _onCheckboxChange(e) {
        this._setVisibility(e.target.checked);
        this._setFieldState(e.target.checked);
    }

    /**
     * Sets the visibilty of the container which contains the different shipping fields depending on the checked state
     * @param {boolean} checked
     * @private
     */
    _setVisibility(checked) {
        if (checked) {
            this.container.classList.add(DIFFERENT_SHIPPING_SHOW_CLASS);
        } else {
            this.container.classList.remove(DIFFERENT_SHIPPING_SHOW_CLASS);
        }
    }

    /**
     * Sets the required and disabled state of the fields inside the container depending on the checked state
     * @param {boolean} checked
     * @private
     */
    _setFieldState(checked) {
        const fields = this.container.querySelectorAll('input, select');

        if (checked) {
            fields.forEach(field => {
                field.removeAttribute('disabled');

                if (field.classList.contains(DIFFERENT_SHIPPING_REQUIRED_CLASS)) {
                    field.setAttribute('required', 'required');
                }
            });
        } else {
            fields.forEach(field => {
                field.removeAttribute('required');
                field.setAttribute('disabled', 'disabled');
            });
        }
    }
}
