import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package checkout
 */
export default class CompanyNameFieldsPlugin extends Plugin {

    static options = {

        /**
         * the selector for the account type select this plugin reacts to
         */
        accountTypeSelector: null,

        /**
         * the account type value that stands for a company account
         */
        businessValue: 'business',

        /**
         * whether the name fields are rendered at all for a company account
         */
        shown: true,

        /**
         * whether the name fields are required for a company account
         */
        required: true,

        /**
         * the class which hides the fields
         */
        hiddenCls: 'd-none',

        /**
         * the selector for the fields inside this element
         */
        fieldSelector: 'input',
    };

    init() {
        this._select = document.querySelector(this.options.accountTypeSelector);
        this._fields = this.el.querySelectorAll(this.options.fieldSelector);

        if (!this._select || this._fields.length === 0) {
            return;
        }

        this._registerEvents();

        // the select can already be set to a company account on load
        this._onChange();
    }

    destroy() {
        if (this._select && this._boundOnChange) {
            this._select.removeEventListener('change', this._boundOnChange);
        }
    }

    _registerEvents() {
        if (!this._boundOnChange) {
            this._boundOnChange = this._onChange.bind(this);
        }

        this._select.removeEventListener('change', this._boundOnChange);
        this._select.addEventListener('change', this._boundOnChange);
    }

    _onChange() {
        const isCompany = this._select.value === this.options.businessValue;
        const shown = !isCompany || this.options.shown;
        const required = !isCompany || (this.options.shown && this.options.required);

        this.el.classList.toggle(this.options.hiddenCls, !shown);

        this._fields.forEach(field => {
            // a hidden field must not be submitted, the route then stores an empty name
            if (shown) {
                field.removeAttribute('disabled');
            } else {
                field.setAttribute('disabled', 'disabled');
            }

            if (required) {
                window.formValidation.setFieldRequired(field);
            } else {
                window.formValidation.setFieldNotRequired(field);
            }
        });

        this.$emitter.publish('onChange', { shown, required });
    }
}
