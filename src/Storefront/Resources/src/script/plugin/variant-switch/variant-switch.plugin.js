import Plugin from 'src/script/plugin-system/plugin.class';
import PageLoadingIndicatorUtil from 'src/script/utility/loading-indicator/page-loading-indicator.util';
import Iterator from 'src/script/helper/iterator.helper';

/**
 * this plugin submits the variant form
 * with the correct data options
 */
export default class VariantSwitchPlugin extends Plugin {

    init() {
        this._ensureFormElement();
        this._preserveCurrentValues();
        this._registerEvents();
    }

    /**
     * ensures that the plugin element is a form
     *
     * @private
     */
    _ensureFormElement() {
        if (this.el.nodeName.toLowerCase() !== 'form') {
            throw new Error('This plugin can only be applied on a form element!');
        }
    }

    /**
     * saves the current value on each form element
     * to be able to retrieve it once it has changed
     *
     * @private
     */
    _preserveCurrentValues() {
        Iterator.iterate(this.el.elements, field => {
            if (VariantSwitchPlugin._isFieldSerializable(field)) {
                if (field.dataset) {
                    field.dataset.variantSwitchValue = field.value;
                }
            }
        });
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.addEventListener('change', event => this._onChange(event));
    }

    /**
     * callback when the form has changed
     *
     * @param event
     * @private
     */
    _onChange(event) {
        const switchedOptionId = this._getSwitchedOptionId(event.target);
        const selectedOptions = this._getFormValue();
        this._preserveCurrentValues();

        this.$emitter.publish('onChange');

        this._submitForm({
            switched: switchedOptionId,
            options: selectedOptions,
        });
    }

    /**
     * returns the option id of the recently switched field
     *
     * @param field
     * @returns {*}
     * @private
     */
    _getSwitchedOptionId(field) {
        if (!VariantSwitchPlugin._isFieldSerializable(field)) {
            return false;
        }

        return field.name;
    }

    /**
     * returns the current selected
     * variant options from the form
     *
     * @private
     */
    _getFormValue() {
        const serialized = {};
        Iterator.iterate(this.el.elements, field => {
            if (VariantSwitchPlugin._isFieldSerializable(field)) {
                if (field.checked) {
                    serialized[field.name] = field.value;
                }
            }
        });

        return serialized;
    }

    /**
     * checks id the field is a value field
     * and therefore serializable
     *
     * @param field
     * @returns {boolean|*}
     *
     * @private
     */
    static _isFieldSerializable(field) {
        return !field.name || field.disabled || ['file', 'reset', 'submit', 'button'].indexOf(field.type) === -1;
    }

    /**
     * disables all form fields on the form submit
     *
     * @private
     */
    _disableFields() {
        Iterator.iterate(this.el.elements, field => {
            if (field.classList) {
                field.classList.add('disabled', 'disabled');
            }
        });
    }

    /**
     * creates the hidden data inputs
     * and submits the form
     *
     * @param data
     * @private
     */
    _submitForm(data) {
        this._disableFields();
        this.el.insertAdjacentHTML('beforeend', `<input type="hidden" name="switched" value="${data.switched}">`);
        this.el.insertAdjacentHTML('beforeend', `<input type="hidden" name="options" value='${JSON.stringify(data.options)}'>`);
        PageLoadingIndicatorUtil.create();

        this.$emitter.publish('beforeSubmitForm');

        this.el.submit();

        this.$emitter.publish('afterSubmitForm');
    }
}
