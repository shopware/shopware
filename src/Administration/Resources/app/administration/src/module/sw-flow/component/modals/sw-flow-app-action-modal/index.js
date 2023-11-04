import template from './sw-flow-app-action-modal.html.twig';
import './sw-flow-app-action-modal.scss';

const { Mixin, Classes: { ShopwareError } } = Shopware;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            config: {},
            fields: [],
            errors: {},
        };
    },

    computed: {
        actionLabel() {
            return this.sequence?.propsAppFlowAction?.translated?.label || this.sequence?.propsAppFlowAction?.label;
        },

        appBadge() {
            return this.sequence?.propsAppFlowAction?.app?.label;
        },

        currentLocale() {
            return Shopware.State.get('session').currentLocale;
        },

        headline() {
            return this.sequence?.propsAppFlowAction?.translated?.headline
                || this.sequence?.propsAppFlowAction?.headline;
        },

        paragraph() {
            return this.sequence?.propsAppFlowAction?.translated?.description
                || this.sequence?.propsAppFlowAction?.description;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getFields();
            if (!this.sequence?.config) {
                return;
            }

            Object.entries({ ...this.sequence.config }).forEach(([key, configValue]) => {
                this.config[key] = (typeof configValue === 'object' && configValue.value !== undefined)
                    ? configValue.value
                    : configValue;
            });
        },

        onChange(event, field) {
            this.handleValid(field, event);
        },

        isValid() {
            this.errors = {};
            this.fields.forEach(field => {
                const val = this.config[field.name] ?? null;
                this.handleValid(field, val);
            });

            return Object.keys(this.errors).length === 0;
        },

        handleValid(field, val) {
            let value = val;
            if (typeof value === 'object' && (value?.length === 0 || value?.value?.length === 0)) {
                value = null;
            }

            if (field.required && !value && typeof value !== 'boolean') {
                this.$delete(this.config, [field.name]);
                this.$set(this.errors, field.name, new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }));
                return;
            }

            this.$delete(this.errors, [field.name]);
        },

        onSave() {
            if (!this.isValid() || !this.sequence?.propsAppFlowAction?.app?.active) return;

            const config = this.buildConfig();
            const data = {
                ...this.sequence,
                config,
            };
            this.$emit('process-finish', data);
            this.onClose();
        },

        buildConfig() {
            const data = {};
            this.fields.forEach(field => {
                if (this.config[field.name]?.length !== 0 && this.config[field.name] !== null) {
                    data[field.name] = this.config[field.name];
                }
            });

            return data;
        },

        onClose() {
            this.$emit('modal-close');
        },

        getFields() {
            this.sequence.propsAppFlowAction?.config.forEach((config) => {
                this.config[config.name] = this.convertDefaultValue(config.type, config.defaultValue);
                this.fields.push(config);
                this.$delete(this.errors, config.name);
            });
        },

        convertDefaultValue(type, value) {
            if (value === undefined) {
                return null;
            }

            if (['int', 'float'].includes(type)) {
                return parseInt(value, 10);
            }

            if (['bool', 'checkbox'].includes(type)) {
                return !!value;
            }

            if (['date', 'datetime', 'time'].includes(type)) {
                return null;
            }

            return value;
        },

        getConfig(field) {
            const config = {
                label: field.label,
                placeholder: field.placeHolder,
                disabled: field.disabled,
                required: field.required,
                helpText: this.helpText(field),
            };

            if (field.type === 'colorpicker') {
                config.componentName = 'sw-colorpicker';
                config.zIndex = 1000;
                config.colorOutput = 'hex';

                return config;
            }

            if (field.type === 'text-editor') {
                config.componentName = 'sw-text-editor';

                return config;
            }

            if (['single-select', 'multi-select'].includes(field.type)) {
                config.componentName = `sw-${field.type}`;
                config.options = field.options;
            }

            return config;
        },

        helpText(field) {
            if (field.helpText === undefined) {
                return null;
            }

            const objHelpText = JSON.parse(JSON.stringify(field.helpText));

            return objHelpText[this.currentLocale] ?? objHelpText[Shopware.Context.app.fallbackLocale] ?? null;
        },
    },
};
