/**
 * @package admin
 */
import createCriteriaFromArray from '../service/criteria-helper.service';
import convertUnit from '../../module/sw-settings-rule/utils/unit-conversion.utils';

const { Mixin } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Mixin.register('generic-condition', {
    data() {
        return {
            visibleValue: null,
            baseUnit: null,
            selectedUnit: null,
        };
    },

    computed: {
        config() {
            const config = Shopware.State.getters['ruleConditionsConfig/getConfigForType'](this.condition.type);

            if (!config) {
                return { operatorSet: null, fields: [] };
            }

            return config;
        },

        inputKey() {
            if (!this.config.fields.length) {
                return null;
            }

            return this.config.fields[0].name;
        },

        operators() {
            if (!this.config.operatorSet) {
                return null;
            }

            return this.conditionDataProviderService.getOperatorOptionsByIdentifiers(
                this.config.operatorSet.operators,
                this.config.operatorSet.isMatchAny,
            );
        },

        /**
         * This computed property serves the purpose of dynamically matching the values of the condition from the config.
         * From the config we create nested properties with their own getters/setters. We iterate the `fields` objects
         * that contain the properties `type` and `name` for each field. The `type` property is used to determine
         * the initial value of the nested property within the getter. The `name` property is used as the key within
         * the `rule_condition` entities `value` object, setting/getting the actual values accordingly.
         */
        values() {
            const values = {};

            Object.values(this.config.fields).forEach((field) => {
                const { name, type } = field;

                Object.defineProperty(values, name, {
                    get: () => {
                        this.ensureValueExist();

                        if (['multi-entity-id-select', 'multi-select'].includes(type)) {
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

            Object.values(this.config.fields).forEach((config) => {
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

        boolOptions() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },

        conditionValueClasses() {
            return {
                'sw-condition__condition-value': !!this.config.operatorSet,
                [`sw-condition__condition-type-${this.condition.type}`]: true,
            };
        },
    },

    methods: {
        getBind(field) {
            const fieldClone = Shopware.Utils.object.cloneDeep(field);
            const snippetBasePath = ['global', 'sw-condition-generic', this.condition.type, fieldClone.name];
            const placeholderPath = [...snippetBasePath, 'placeholder'].join('.');

            if (['multi-entity-id-select', 'single-entity-id-select'].includes(fieldClone.type)
                && fieldClone.config.criteria) {
                fieldClone.config.criteria = createCriteriaFromArray(fieldClone.config.criteria);
            }

            if (fieldClone.type === 'single-select' && fieldClone.config.options) {
                fieldClone.config.options = fieldClone.config.options.map((value) => {
                    return {
                        label: this.$tc([...snippetBasePath, 'options', value].join('.')),
                        value,
                    };
                });
            }

            if (fieldClone.type === 'bool') {
                fieldClone.type = 'single-select';
                fieldClone.config.options = this.boolOptions;
            }

            if (this.$te(placeholderPath)) {
                fieldClone.config.placeholder = this.$tc(placeholderPath);
            }

            fieldClone.config.name = `sw-field--${fieldClone.name}`;

            return fieldClone;
        },

        updateFieldValue(fieldName, value, to = undefined, from = undefined) {
            if (!from || !to || from === to) {
                this.$set(this.values, fieldName, value);

                return;
            }

            this.$set(this.values, fieldName, convertUnit(value, {
                from,
                to,
            }));
        },

        updateVisibleValue(value) {
            this.visibleValue = value;
        },

        getVisibleValue(fieldName) {
            if (this.visibleValue === null) {
                return this.values[fieldName];
            }

            return this.visibleValue;
        },

        handleUnitChange(event) {
            this.selectedUnit = event.unit;

            this.updateVisibleValue(event.value);
        },

        /**
         * @param event represents the base unit
         */
        setDefaultUnit(event) {
            this.baseUnit = event;
        },
    },
});
