import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';


export default class FormFieldTogglePlugin extends Plugin {

    static options = {

        /**
         * the class which should be applied
         * when the target should be hidden or shown
         */
        hiddenCls: 'd-none',
        showCls: 'd-block',

        /**
         * the attribute for the target selector
         */
        targetDataAttribute: 'data-form-field-toggle-target',

        /**
         * on which value the target should be hidden
         */
        valueDataAttribute: 'data-form-field-toggle-value',

        /**
         * the class which gets applied
         * when the field previously had the required attribute
         */
        wasRequiredCls: 'js-field-toggle-was-required',

        /**
         * the class which gets applied
         * when the field previously had the disabled attribute
         */
        wasDisabledCls: 'js-field-toggle-was-disabled'
    };

    init() {
        this._getTargets();
        this._getControlValue();
        this._registerEvents();

        // Since the target could be hidden from the start,
        // the onChange function must be called.
        this._onChange();
    }

    /**
     * sets the list of targets
     * found be the passed selector
     *
     * @private
     */
    _getTargets() {
        const selector = DomAccess.getDataAttribute(this.el, this.options.targetDataAttribute);
        this._targets = DomAccess.querySelectorAll(document, selector);
    }

    /**
     * sets the value on which the the
     * targets should be toggled
     *
     * @private
     */
    _getControlValue() {
        this._value = DomAccess.getDataAttribute(this.el, this.options.valueDataAttribute);
    }

    /**
     * registers all needed events
     *
     * @private
     */
    _registerEvents() {
        this.el.removeEventListener('change', this._onChange.bind(this));
        this.el.addEventListener('change', this._onChange.bind(this));
    }

    /**
     * on change callback for the element
     *
     * @private
     */
    _onChange() {
        const shouldShow = this._shouldShowTarget();

        Iterator.iterate(this._targets, node => {
            if (shouldShow) {
                this._showTarget(node);
            } else {
                this._hideTarget(node);
            }
        });

        this.$emitter.publish('onChange');
    }

    /**
     * returns whether or not the
     * target should be hidden
     *
     * @returns {*}
     * @private
     */
    _shouldShowTarget() {
        const type = this.el.type;
        if (type === 'checkbox' || type === 'radio') {
            return this.el.checked === this._value;
        } else {
            return this.el.value === this._value;
        }
    }

    /**
     * hides the given target element
     *
     * @param target
     * @private
     */
    _hideTarget(target) {
        const fields = this._getFields(target);
        Iterator.iterate(fields, field => {
            const isRequired = DomAccess.hasAttribute(field, 'required');
            if (isRequired) {
                field.classList.add(this.options.wasRequiredCls);
                field.removeAttribute('required');
            }

            field.setAttribute('disabled', 'disabled');
            const isDisabled = DomAccess.hasAttribute(field, 'disabled');
            if (isDisabled) {
                field.classList.remove(this.options.wasDisabledCls);
            }
        });

        target.classList.remove(this.options.showCls);
        target.classList.add(this.options.hiddenCls);
    }

    /**
     * shows the given target element
     *
     * @param target
     * @private
     */
    _showTarget(target) {
        const fields = this._getFields(target);
        Iterator.iterate(fields, field => {
            if (field.classList.contains(this.options.wasRequiredCls)) {
                field.classList.remove(this.options.wasRequiredCls);
                field.setAttribute('required', 'required');
            }

            const wasDisabled = DomAccess.hasAttribute(field, 'disabled');
            if (wasDisabled) {
                field.removeAttribute('disabled');
                field.classList.add(this.options.wasDisabledCls);
            }
        });

        target.classList.remove(this.options.hiddenCls);
        target.classList.add(this.options.showCls);
    }

    /**
     * returns all fields inside the form
     *
     * @param target
     * @returns {NodeList|false}
     *
     * @private
     */
    _getFields(target) {
        return DomAccess.querySelectorAll(target, 'input, select, textarea', false);
    }
}
