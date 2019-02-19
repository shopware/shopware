import { Component } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';
import template from './sw-form-field-renderer.html.twig';

/**
 * @public
 * @status ready
 * @description
 * Dynamically renders components. At first it checks for the componentName prop to choose which component to
 * render. Next it checks the configuration for a <code>componentName</code>. If a <code>componentName</code>
 * isn't specified, the type prop will be checked to find a suitable component for the type. Everything inside
 * the config prop will be passed to the rendered child prop as properties. Also all additional props will be
 * passed to the child.
 * @example-type code-only
 * @component-example
 * // Datepicker
 * <sw-form-field-renderer type="datetime"></sw-form-field-renderer>
 *
 * // Text field
 * <sw-form-field-renderer type="string"></sw-form-field-renderer>
 *
 * // sw-colorpicker
 * <sw-form-field-renderer componentName="sw-colorpicker" type="string"></sw-form-field-renderer>
 *
 * // sw-number-field
 * <sw-form-field-renderer type="string" config="{ type="number", numberType="float" }"></sw-form-field-renderer>
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
            swFieldConfig: {},
            currentValue: ''
        };
    },

    computed: {
        component() {
            if (this.componentName) {
                return this.validateComponentName(this.componentName);
            }

            if (this.config && this.config.componentName) {
                return this.validateComponentName(this.config.componentName);
            }

            return this.getComponentFromType();
        }
    },

    watch: {
        currentValue(value) {
            this.$emit('input', value);
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currentValue = this.value;
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
                this.swFieldConfig = { ...this.swFieldConfig, ...{ type: 'checkbox' } };
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

            throw new Error(`sw-form-field-renderer - No suitable component for type "${this.type}" found`);
        },
        validateComponentName(name) {
            const componentRegistry = Component.getComponentRegistry();

            if (componentRegistry.has(name)) {
                return name;
            }

            throw new Error(`sw-form-field-renderer - Component with name "${name}" not found`);
        },
        getBind() {
            let bind = this.$attrs;
            bind = { ...bind, ...deepCopyObject(this.config) };

            if (this.type && !bind.type) {
                bind = { ...bind, ...{ type: this.type } };
            }

            if (Object.keys(this.swFieldConfig).length > 0) {
                bind = { ...bind, ...this.swFieldConfig };
            }

            // Set the name as label if no label is passed
            if (!bind.label) {
                bind.label = bind.name;
            }

            return bind;
        }
    }
};
