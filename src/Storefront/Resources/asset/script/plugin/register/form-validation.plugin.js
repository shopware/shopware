import Plugin from 'asset/script/helper/plugin/plugin.class';

const FORM_WAS_VALIDATED_CLASS = 'was-validated';

export default class FormValidation extends Plugin {

    init() {
        if (this._isFormElement() === false) {
            throw Error('Element is not of type <form>');
        }

        this._prepareForm();
        this._registerEvents();
    }

    /**
     * Verify whether the plugin element is of type <form> or not
     * @returns {boolean}
     * @private
     */
    _isFormElement() {
        return (this.el.tagName.toLowerCase() === 'form');
    }

    /**
     * Prepares the form for custom Bootstrap form validation
     *
     * @private
     */
    _prepareForm() {
        this.el.setAttribute('novalidate', '');
    }

    /**
     * Registers all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    /**
     * Checks form validity before submit
     * @param {Event} e
     * @private
     */
    _onFormSubmit(e) {
        if (this.el.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
        }

        this.el.classList.add(FORM_WAS_VALIDATED_CLASS);
    }
}
