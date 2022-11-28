import template from './sw-condition-script.html.twig';
import './sw-condition-script.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @public
 * @package business-ops
 * @description Condition for the ScriptRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-script :condition="condition" :level="0"></sw-condition-script>
 */
Component.extend('sw-condition-script', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    computed: {
        config() {
            if (!this.condition.appScriptCondition) {
                return [];
            }

            return this.condition.appScriptCondition.config;
        },

        values() {
            const values = {};

            // Iterate the config of fields to define nested reactive properties with getters/setters for values
            Object.values(this.config).forEach((field) => {
                const { name, type, config } = field;

                Object.defineProperty(values, name, {
                    get: () => {
                        this.ensureValueExist();

                        if (type === 'bool' && !this.condition.value.hasOwnProperty(name)) {
                            this.condition.value = { ...this.condition.value, [name]: false };
                        }

                        if (['sw-entity-multi-id-select', 'sw-multi-select'].includes(config.componentName)) {
                            return this.condition.value[name] || [];
                        }

                        return this.condition.value[name];
                    },
                    set: (value) => {
                        this.ensureValueExist();
                        this.condition.value = { ...this.condition.value, [name]: value };
                    },
                });
            });

            return values;
        },

        currentError() {
            let error = null;

            Object.values(this.config).forEach((config) => {
                if (error) {
                    return;
                }

                const errorProperty = Shopware.State.getters['error/getApiError'](this.condition, `value.${config.name}`);

                if (errorProperty) {
                    error = errorProperty;
                }
            });

            return error;
        },

        conditionClasses() {
            return {
                'has--operator-first': this.config.length > 1 && this.config[0].name === 'operator',
            };
        },
    },

    methods: {
        getBind(field) {
            const fieldClone = Shopware.Utils.object.cloneDeep(field);

            if (fieldClone.type === 'html') {
                fieldClone.type = 'text';
                fieldClone.config.componentName = 'sw-field';
                fieldClone.config.type = 'text';
                fieldClone.config.customFieldType = 'text';
            }

            if (fieldClone.type === 'price') {
                fieldClone.type = 'float';
                fieldClone.config.componentName = 'sw-field';
                fieldClone.config.type = 'number';
                fieldClone.config.customFieldType = 'number';
            }

            if (fieldClone.type === 'entity' && fieldClone.config.entity === 'product') {
                const criteria = new Criteria(1, 25);

                criteria.addAssociation('options.group');

                fieldClone.config.criteria = criteria;
                fieldClone.config.displayVariants = true;
            }

            delete fieldClone.config.label;
            delete fieldClone.config.helpText;

            return fieldClone;
        },

        updateFieldValue(fieldName, value) {
            this.$set(this.values, fieldName, value);
        },
    },
});
