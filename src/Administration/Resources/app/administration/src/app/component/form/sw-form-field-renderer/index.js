import template from './sw-form-field-renderer.html.twig';

const { Component, Mixin } = Shopware;
const { types } = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description
 * Dynamically renders components with a given configuration. The rendered component can be forced by defining
 * the config.componentName property. If not set the form-field-renderer will guess a suitable
 * component for the type. Everything inside the config prop will be passed to the rendered child prop as properties.
 * Also all additional props will be passed to the child.
 * @example-type code-only
 * @component-example
 * {# Datepicker #}
 * <sw-form-field-renderer
 *         type="datetime"
 *         v-model="yourValue">
 * </sw-form-field-renderer>
 *
 * {# Text field #}
 * <sw-form-field-renderer
 *         type="string"
 *         v-model="yourValue">
 * </sw-form-field-renderer>
 *
 * {# sw-number-field #}
 * <sw-form-field-renderer
 *         :config="{
 *             componentName: 'sw-field',
 *             type: 'number',
 *             numberType: 'float'
 *         }"
 *         v-model="yourValue">
 * </sw-form-field-renderer>
 *
 * {# sw-select - multi #}
 * <sw-form-field-renderer
 *         :config="{
 *             componentName: 'sw-multi-select',
 *             label: {
 *                 'en-GB': 'Multi Select'
 *             },
 *             multi: true,
 *             options: [
 *                 { value: 'option1', label: { 'en-GB': 'One' } },
 *                 { value: 'option2', label: 'Two' },
 *                 { value: 'option3', label: { 'en-GB': 'Three', 'de-DE': 'Drei' } }
 *             ]
 *         }"
 *         v-model="yourValue">
 * </sw-form-field-renderer>
 *
 * {# sw-select - single #}
 * <sw-form-field-renderer
 *         :componentName: 'sw-single-select',
 *         :config="{
 *             label: 'Single Select',
 *             options: [
 *                 { value: 'option1', label: { 'en-GB': 'One' } },
 *                 { value: 'option2', label: 'Two' },
 *                 { value: 'option3', label: { 'en-GB': 'Three', 'de-DE': 'Drei' } }
 *             ]
 *         }"
 *         v-model="yourValue">
 * </sw-form-field-renderer>
 */
Component.register('sw-form-field-renderer', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        type: {
            type: String,
            required: false,
            default: null,
        },
        config: {
            type: Object,
            required: false,
            default: null,
        },
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },
    },

    data() {
        return {
            currentComponentName: '',
            swFieldConfig: {},
            currentValue: this.value,
        };
    },

    computed: {
        bind() {
            const bind = {
                ...this.$attrs,
                ...this.config,
                ...this.swFieldType,
                ...this.translations,
                ...this.optionTranslations,
            };

            if (this.componentName === 'sw-entity-multi-id-select') {
                bind.repository = this.createRepository(this.config.entity);
            }

            return bind;
        },

        hasConfig() {
            return !!this.config;
        },

        componentName() {
            if (this.hasConfig) {
                return this.config.componentName || this.getComponentFromType();
            }
            return this.getComponentFromType();
        },

        swFieldType() {
            if (this.componentName !== 'sw-field'
                || (this.hasConfig && this.config.hasOwnProperty('type'))) {
                return {};
            }

            if (this.type === 'int') {
                return { type: 'number', numberType: 'int' };
            }

            if (this.type === 'float') {
                return { type: 'number', numberType: 'float' };
            }

            if (this.type === 'string' || this.type === 'text') {
                return { type: 'text' };
            }

            if (this.type === 'bool') {
                return { type: 'switch', bordered: true };
            }

            if (this.type === 'datetime') {
                return { type: 'date', dateType: 'datetime' };
            }

            if (this.type === 'date') {
                return { type: 'date', dateType: 'date' };
            }

            if (this.type === 'time') {
                return { type: 'date', dateType: 'time' };
            }

            return { type: this.type };
        },

        translations() {
            return this.getTranslations(this.componentName);
        },

        optionTranslations() {
            if (['sw-single-select', 'sw-multi-select'].includes(this.componentName)) {
                if (!this.config.hasOwnProperty('options')) {
                    return {};
                }

                const options = [];
                let labelProperty = 'label';

                // Use custom label property if defined
                if (this.config.hasOwnProperty('labelProperty')) {
                    labelProperty = this.config.labelProperty;
                }

                this.config.options.forEach(option => {
                    const translation = this.getTranslations(
                        'options',
                        option,
                        [labelProperty],
                    );
                    // Merge original option with translation
                    const translatedOption = { ...option, ...translation };
                    options.push(translatedOption);
                });

                return { options };
            }

            return {};
        },
    },

    watch: {
        currentValue(value) {
            if (value !== this.value) {
                this.$emit('input', value);
            }
        },
        value() {
            this.currentValue = this.value;
        },
    },

    methods: {
        getTranslations(componentName, config = this.config, translatableFields = ['label', 'placeholder', 'helpText']) {
            if (!translatableFields) {
                return {};
            }

            const translations = {};
            translatableFields.forEach((field) => {
                if (config[field] && config[field] !== '') {
                    translations[field] = this.getInlineSnippet(config[field]);
                }
            });

            return translations;
        },

        getComponentFromType() {
            if (this.type === 'single-select') {
                return 'sw-single-select';
            }

            if (this.type === 'multi-select') {
                return 'sw-multi-select';
            }

            return 'sw-field';
        },

        createRepository(entity) {
            if (types.isUndefined(entity)) {
                throw new Error('sw-form-field-renderer - sw-entity-multi-id-select component needs entity property');
            }

            return this.repositoryFactory.create(entity);
        },
    },
});
