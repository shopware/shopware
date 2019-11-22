import template from './sw-form-field-renderer.html.twig';
import './sw-form-field-renderer.scss';

const { Component, Mixin } = Shopware;
const { LocalStore } = Shopware.DataDeprecated;

/**
 * @public
 * @status ready
 * @description
 * Dynamically renders components with a given configuration. The rendered component can be forced by defining
 * the config.componentName porperty. If not set the form-field-renderer will guess a suitable
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

    mixins: [
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        type: {
            type: String,
            required: false
        },
        config: {
            type: Object,
            required: false
        },
        value: {
            required: true
        }
    },

    data() {
        return {
            currentComponentName: '',
            swFieldConfig: {},
            currentValue: this.value,
            translatedFields: {
                'sw-field': ['label', 'placeholder', 'helpText'],
                'sw-text-editor': ['label', 'placeholder', 'helpText'],
                'sw-media-field': ['label'],
                'sw-select': ['label', 'placeholder', 'helpText'],
                'sw-single-select': ['label', 'placeholder', 'helpText'],
                'sw-multi-select': ['label', 'placeholder', 'helpText'],
                'sw-entity-single-select': ['label', 'placeholder', 'helpText']
            }
        };
    },

    computed: {
        bind() {
            const bind = {
                ...this.$attrs,
                ...this.config,
                ...this.swFieldType,
                ...this.translations,
                ...this.optionTranslations
            };

            // create stores for sw-select
            if (this.componentName === 'sw-select') {
                this.addSwSelectStores(bind);
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

            return { type: this.type };
        },

        translations() {
            return this.getTranslations(this.componentName);
        },

        optionTranslations() {
            if (['sw-single-select', 'sw-multi-select'].includes(this.componentName)) {
                if (this.config.hasOwnProperty('options')) {
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
                            [labelProperty]
                        );
                        // Merge original option with translation
                        const translatedOption = { ...option, ...translation };
                        options.push(translatedOption);
                    });

                    return { options };
                }
            }
            return {};
        }
    },

    watch: {
        currentValue(value) {
            if (value !== this.value) {
                this.$emit('input', value);
            }
        },
        value() {
            this.currentValue = this.value;
            // Recreate select association store on value changes and reload selections,
            // this is necessary for languages changes for example
            if (this.componentName === 'sw-select') {
                if (this.bind.multi) {
                    this.addSwSelectAssociationStore(this.bind, true);
                }
                this.refreshSwSelectSelections();
            }
        }
    },

    methods: {
        getTranslations(componentName, config = this.config, translatableFields = this.translatedFields[componentName]) {
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

            if (this.type === 'media') {
                return 'sw-media-field';
            }

            if (this.type === 'entity-single-select') {
                return 'sw-entity-single-select';
            }

            return 'sw-field';
        },

        addSwSelectStores(bind) {
            if (bind.store) {
                return;
            }

            if (this.config.options.length < 1) {
                throw new Error('sw-form-field-renderer - sw-select component needs options or a store');
            }

            bind.store = new LocalStore([], 'id', 'name');
            this.config.options.forEach(({ id, name }) => {
                bind.store.add({ id, name: this.getInlineSnippet(name) });
            });

            if (bind.multi) {
                this.addSwSelectAssociationStore(bind, false);
            }

            this.refreshSwSelectSelections();
        },
        addSwSelectAssociationStore(bind, override) {
            if (bind.associationStore && override === false) {
                return;
            }
            const entities = [];
            if (this.value && this.value.length > 0) {
                this.value.forEach((value) => {
                    entities.push(bind.store.getById(value));
                });
            }
            bind.associationStore = new LocalStore(entities);
        },
        refreshSwSelectSelections() {
            this.$nextTick(() => {
                if (this.$refs.component) {
                    this.$refs.component.loadSelected(true);
                }
            });
        }
    }
});
