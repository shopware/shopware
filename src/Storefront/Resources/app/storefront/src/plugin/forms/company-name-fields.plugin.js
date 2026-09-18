import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package checkout
 */
export default class CompanyNameFieldsPlugin extends Plugin {

    static options = {

        accountTypeSelector: null,

        businessValue: 'business',

        shown: true,

        required: true,

        hiddenCls: 'd-none',

        fieldSelector: 'input',
    };

    init() {
        this._select = document.querySelector(this.options.accountTypeSelector);
        this._fields = this.el.querySelectorAll(this.options.fieldSelector);

        if (!this._select || this._fields.length === 0) {
            return;
        }

        this._registerEvents();

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
