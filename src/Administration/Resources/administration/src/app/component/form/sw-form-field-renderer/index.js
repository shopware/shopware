import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-form-field-renderer.html.twig';

/**
 * @public
 * @status ready
 * @description
 * Dynamically renders components. To find out which component to render it first checks for the componentName
 * prop to choose which component to render. Next it checks the configuration for a <code>componentName</code>.
 * If a <code>componentName</code> isn't specified, the type prop will be checked to automatically guess a suitable
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
 * {# sw-colorpicker #}
 * <sw-form-field-renderer
 *         componentName="sw-colorpicker"
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
 *         :config="{
 *             componentName: 'sw-single-select',
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
export default {
    name: 'sw-form-field-renderer',
    template,

    props: {
        componentName: {
            type: String,
            required: false
        },
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
            currentConfig: {},
            currentValue: ''
        };
    },

    computed: {
        bind() {
            let bind = { ...this.$attrs, ...this.currentConfig };

            if (this.type && !bind.type) {
                bind = { ...bind, ...{ type: this.type } };
            }

            if (Object.keys(this.swFieldConfig).length > 0) {
                bind = { ...bind, ...this.swFieldConfig };
            }

            // create stores for sw-select
            if (this.currentComponentName === 'sw-select') {
                this.addSwSelectStores(bind);
            }

            // Set the name as label if no label is passed
            if (!bind.label) {
                bind.label = bind.name;
            }

            return bind;
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
            if (this.component === 'sw-select') {
                if (this.bind.multi) {
                    this.addSwSelectAssociationStore(this.bind, true);
                }
                this.refreshSwSelectSelections();
            }
        },
        '$attrs.disabled'() {
            this.createBind();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currentValue = this.value;
            this.currentConfig = Object.assign({}, this.config);

            this.currentComponentName = this.getComponentName();
        },
        getComponentName() {
            if (this.componentName) {
                return this.validateComponentName(this.componentName);
            }

            if (this.config && this.config.componentName) {
                return this.validateComponentName(this.config.componentName);
            }

            return this.getComponentFromType();
        },
        getComponentFromType() {
            if (this.type === 'int') {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'number' } };
                this.swFieldConfig = { ...this.swFieldConfig, ...{ numberType: 'int' } };
                return 'sw-field';
            }
            if (this.type === 'string' || this.type === 'text') {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'text' } };
                return 'sw-field';
            }
            if (this.type === 'bool') {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'boolean' } };
                return 'sw-field';
            }
            if (this.type === 'float') {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'number' } };
                this.swFieldConfig = { ...this.swFieldConfig, ...{ numberType: 'float' } };
                return 'sw-field';
            }
            if (this.type === 'datetime') {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'date', dateType: 'datetime' } };
                return 'sw-field';
            }
            if (this.type === 'single-select') {
                return 'sw-single-select';
            }
            if (this.type === 'multi-select') {
                return 'sw-multi-select';
            }
            const swFieldTypes = [
                'text',
                'boolean',
                'checkbox',
                'switch',
                'number',
                'radio',
                'textarea',
                'date',
                'colorpicker',
                'confirm',
                'password',
                'url'
            ];
            if (swFieldTypes.includes(this.type)) {
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: this.type } };
                return 'sw-field';
            }

            throw new Error(`sw-form-field-renderer - No suitable component for type "${this.type}" found`);
        },
        validateComponentName(name) {
            const componentRegistry = Component.getComponentRegistry();

            if (componentRegistry.has(name)) {
                return name;
            }

            throw new Error(`sw-form-field-renderer - Component with name "${name}" not found`);
        },
        addSwSelectStores(bind, override = false) {
            if (bind.store && override === false) {
                return;
            }

            if (this.config.options.length < 1) {
                throw new Error('sw-form-field-renderer - sw-select component needs options or a store');
            }

            bind.store = new LocalStore(this.config.options, 'id', 'name');

            if (bind.multi) {
                this.addSwSelectAssociationStore(bind, override);
            }

            this.refreshSwSelectSelections();
        },
        addSwSelectAssociationStore(bind, override = false) {
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
};
