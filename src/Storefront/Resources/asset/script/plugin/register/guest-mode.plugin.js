import Plugin from 'asset/script/helper/plugin/plugin.class';
import DomAccess from 'asset/script/helper/dom-access.helper';

// TODO: NEXT-2714 - use input-validation helper

const GUEST_MODE_HIDE_CLASS = 'd-none';
const GUEST_MODE_HIDE_CONTAINER_CLASS = 'js-guest-mode-hide';
const GUEST_MODE_REQUIRED_CLASS = 'js-required';

export default class GuestMode extends Plugin {

    init() {
        try {
            this.container = DomAccess.querySelector(document, `.${GUEST_MODE_HIDE_CONTAINER_CLASS}`);
        } catch (e) {
            return;
        }

        this._registerEvents();

        this._setVisibility(this.el.checked);
        this._setFieldState(this.el.checked);
    }

    /**
     * Registers all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('change', this._onCheckboxChange.bind(this));
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
     * Sets the visibilty of the container which contains the email and password confirmation fields depending on the checked state
     * @param {boolean} checked
     * @private
     */
    _setVisibility(checked) {
        if (checked) {
            this.container.classList.add(GUEST_MODE_HIDE_CLASS);
        } else {
            this.container.classList.remove(GUEST_MODE_HIDE_CLASS);
        }
    }

    /**
     * Sets the required and disabled state of the fields inside the container depending on the checked state
     * @param {boolean} checked
     * @private
     */
    _setFieldState(checked) {
        const fields = this.container.querySelectorAll('input');

        if (!checked) {
            fields.forEach(field => {
                field.removeAttribute('disabled');

                if (field.classList.contains(GUEST_MODE_REQUIRED_CLASS)) {
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
