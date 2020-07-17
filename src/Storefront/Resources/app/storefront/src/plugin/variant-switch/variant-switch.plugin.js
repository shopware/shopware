import Plugin from 'src/plugin-system/plugin.class';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';
import HttpClient from 'src/service/http-client.service';
import queryString from 'query-string';

/**
 * this plugin submits the variant form
 * with the correct data options
 */
export default class VariantSwitchPlugin extends Plugin {

    static options = {
        url: '',
        radioFieldSelector: '.product-detail-configurator-option-input'
    };

    init() {
        this._httpClient = new HttpClient();
        this._radioFields = DomAccess.querySelectorAll(this.el, this.options.radioFieldSelector);

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
        Iterator.iterate(this._radioFields, field => {
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

        this._redirectToVariant({
            switched: switchedOptionId,
            options: selectedOptions
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
        Iterator.iterate(this._radioFields, field => {
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
        Iterator.iterate(this._radioFields, field => {
            if (field.classList) {
                field.classList.add('disabled', 'disabled');
            }
        });
    }

    /**
     * gets the url of the new variant
     * and redirects to this url
     *
     * @param {Object} data
     * @private
     */
    _redirectToVariant(data) {
        PageLoadingIndicatorUtil.create();

        data.options = JSON.stringify(data.options);

        const url = this.options.url + '?' + queryString.stringify(data);

        this._httpClient.get(`${url}`, (response) => {
            const data = JSON.parse(response);
            window.location.replace(data.url);
        });
    }
}
