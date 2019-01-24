import { Mixin, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-field.html.twig';
import './sw-field.less';

/**
 * @public
 * @description Universal field component which supports all common types of input fields.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-field type="text" label="Name" placeholder="Enter your name..."></sw-field>
 */
export default {
    name: 'sw-field',
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    props: {
        type: {
            type: String,
            required: false,
            default: 'text'
        },
        label: {
            type: String,
            required: false,
            default: ''
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        suffix: {
            type: String,
            required: false,
            default: ''
        },
        value: {
            required: false,
            default: null
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        errorMessage: {
            type: String,
            required: false,
            default: null
        },
        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            }
        },
        copyAble: {
            type: Boolean,
            required: false,
            default: false
        },
        passwordToggleAble: {
            type: Boolean,
            required: false,
            default: true
        },
        datepickerConfig: {
            type: Object,
            default: () => {
                return {};
            }
        }
    },

    data() {
        return {
            currentValue: null,
            boundExpression: '',
            boundExpressionPath: [],
            showPassword: false,
            formError: {},
            /** Array of white listed key codes from 0 to 9 including comma and period */
            whitelistNumberKeys: [44, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57]
        };
    },

    computed: {
        hasSuffix() {
            return this.suffix.length || !!this.$slots.suffix;
        },

        isTypeDate() {
            return (this.type === 'date' ||
                    this.type === 'date-local');
        },

        isTypeTime() {
            return (this.type === 'time' ||
                    this.type === 'time-local');
        },

        isTypeDateTime() {
            return (this.type === 'datetime' ||
                    this.type === 'datetime-local');
        },

        isTypeGenericDate() {
            return (this.isTypeDate ||
                    this.isTypeTime ||
                    this.isTypeDateTime);
        },

        displayName() {
            if (this.$attrs.name && this.$attrs.name.length > 0) {
                return this.$attrs.name;
            }

            return `sw-field--${this.boundExpression.replace('.', '-')}`;
        },

        id() {
            return `sw-field--${utils.createId()}`;
        },

        errorStore() {
            return State.getStore('error');
        },

        hasError() {
            return (this.errorMessage !== null && this.errorMessage.length > 0) ||
                   (this.formError.detail && this.formError.detail.length > 0);
        },

        hasErrorCls() {
            return !this.isValid || this.hasError;
        },

        additionalEventListeners() {
            const listeners = {};

            /**
             * Do not pass "change" or "input" event listeners to the form elements
             * because the component implements its own listeners for this event types.
             * The callback methods will emit the corresponding event to the parent.
             */
            Object.keys(this.$listeners).forEach((key) => {
                if (!['change', 'input'].includes(key)) {
                    listeners[key] = this.$listeners[key];
                }
            });

            return listeners;
        },

        fieldClasses() {
            return [
                `sw-field--${this.type}`,
                {
                    'has--error': !!this.hasErrorCls,
                    'has--suffix': !!(this.hasSuffix || this.$props.copyAble || this.isTypeGenericDate),
                    'is--disabled': !!this.$props.disabled
                }];
        }
    },

    watch: {
        value(value) {
            this.currentValue = this.convertValueType(value);
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.currentValue = this.convertValueType(this.value);

            if (this.$vnode.data && this.$vnode.data.model) {
                this.boundExpression = this.$vnode.data.model.expression;
                this.boundExpressionPath = this.boundExpression.split('.');

                if (this.errorStore) {
                    this.formError = this.errorStore.registerFormField(this.boundExpression);
                }
            }
        },

        onInput(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('input', this.currentValue);

            if (this.hasError) {
                this.errorStore.deleteError(this.formError);
            }
        },

        onKeyPress(event) {
            const key = event.key;
            let code = 0;
            if (key !== undefined) {
                code = key.charCodeAt(0);
            }

            if (this.type === 'number') {
                if (this.whitelistNumberKeys.indexOf(code) === -1) {
                    event.preventDefault();
                }
            }
        },

        onChange(event) {
            this.currentValue = this.getValueFromEvent(event);

            this.$emit('change', this.currentValue);

            if (['checkbox', 'radio', 'switch', 'boolean'].includes(this.type)) {
                this.$emit('input', this.currentValue);
            }

            if (this.hasError) {
                this.errorStore.deleteError(this.formError);
            }
        },

        /**
         * Get the correct value from a input event based on the input type.
         *
         * @param event
         * @returns {*}
         */
        getValueFromEvent(event) {
            if (this.isTypeGenericDate) {
                return this.convertValueType(event);
            }

            let value = event.target.value;

            if (event.target.type === 'checkbox') {
                value = event.target.checked;
            }

            return this.convertValueType(value);
        },

        /**
         * Convert the value to the correct type based on the bound property.
         *
         * @param value
         * @returns {*}
         */
        convertValueType(value) {
            if (!value || typeof value === 'undefined' || value === null) {
                if (this.isTypeGenericDate) {
                    return '';
                }

                return null;
            }

            if (this.type === 'number') {
                if (typeof value === 'string' && value.length <= 0) {
                    return null;
                }

                return parseFloat(value);
            }

            if (this.type === 'checkbox' || this.type === 'switch') {
                return value === 'true' || value === true;
            }

            if (typeof value === 'string' && value.length <= 0) {
                return null;
            }

            // Datetime field does not support time zones
            if ((this.type === 'datetime' || this.type === 'datetime-local') && typeof value === 'string') {
                value = value.split('+')[0];
            }

            return value;
        },

        onTogglePasswordVisibility() {
            this.showPassword = !this.showPassword;
        },

        copyToClipboard() {
            const el = this.$refs.textfield;
            if (this.disabled) {
                el.removeAttribute('disabled');
            }

            el.select();

            try {
                document.execCommand('copy');
                this.createNotificationInfo({
                    title: this.$tc('global.sw-field.notification.notificationCopySuccessTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopySuccessMessage')
                });
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('global.sw-field.notification.notificationCopyFailureTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage')
                });
            }

            window.getSelection().removeAllRanges();
            if (this.disabled) {
                el.setAttribute('disabled', 'disabled');
            }
        },

        addKeyCodeToWhitelistNumberKeys(keyCode) {
            this.whitelistNumberKeys.push(keyCode);
        },

        removeKeyCodeFromWhitelistNumberKeys(keyCode) {
            this.whitelistNumberKeys = this.whitelistNumberKeys.filter((code) => {
                return code !== keyCode;
            });
        },

        onSuffixClick() {
            if (!this.isTypeGenericDate) {
                return;
            }

            this.$refs.swDatepicker.openDatepicker();
        }
    }
};
