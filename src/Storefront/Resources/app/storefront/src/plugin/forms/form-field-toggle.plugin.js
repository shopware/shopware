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
         * The default value for the scope
         */
        scopeAll: 'all',

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
        wasDisabledCls: 'js-field-toggle-was-disabled',

        /**
         * The data attribute which contains the scope.
         * The scope defines on which element the selector from `targetDataAttribute` applies.
         * In case of 'all', the selector is queried against the whole document.
         * In case of 'parent', the selector is queried against the given selector.
         */
        scopeDataAttribute: 'data-form-field-toggle-scope',

        /**
         * The data attribute to contain the selector for the parent element.
         * This is only used if the scope, defined in the data attribute of `scopeDataAttribute`, is not set to 'all',
         * which is the default.
         */
        parentSelectorDataAttribute: 'data-form-field-toggle-parent-selector',

        /**
         * The data attribute to contain a boolean value that declares if on nested instances of `FormFieldTogglePlugin`
         * the method `_onChange` should be called when iterating elements to be shown. This is meant for instances
         * where form fields should not automatically be required but be dependent on a nested instance of `FormFieldTogglePlugin`
         */
        triggerNestedDataAttribute: 'data-form-field-toggle-trigger-nested',
    };

    init() {
        this._getTargets();
        this._getControlValue();
        this._registerEvents();

        // Since the target could be hidden from the start,
        // the onChange function must be called.
        this._onChange();

        this._triggerNested = DomAccess.getDataAttribute(this.el, this.options.triggerNestedDataAttribute, false);
    }

    /**
     * sets the list of targets
     * found be the passed selector
     *
     * @private
     */
    _getTargets() {
        const selector = DomAccess.getDataAttribute(this.el, this.options.targetDataAttribute);
        const scope = DomAccess.getDataAttribute(this.el, this.options.scopeDataAttribute, false) || this.options.scopeAll;

        if (scope === this.options.scopeAll) {
            this._targets = DomAccess.querySelectorAll(document, selector);

            return;
        }

        const parentEl = this.el.closest(DomAccess.getDataAttribute(this.el, this.options.parentSelectorDataAttribute));
        this._targets = DomAccess.querySelectorAll(parentEl, selector);
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
        if (this._triggerNested) {
            Iterator.iterate(fields, field => {
                if (field.matches('[data-form-field-toggle="true"]')) {
                    const instance = window.PluginManager.getPluginInstanceFromElement(field, 'FormFieldToggle');

                    if (instance) {
                        instance._onChange();
                    }
                }
            });
        }

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
