/**
 * @package admin
 */
import type Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';
import createCriteriaFromArray from '../service/criteria-helper.service';
import convertUnit from '../../module/sw-settings-rule/utils/unit-conversion.utils';

const { Mixin } = Shopware;

interface Field {
    name: string,
    type: string,
    config: {
        name: string;
        criteria: Criteria,
        options: unknown[],
        placeholder: string,
    }
}
interface Config {
    operatorSet: null,
    fields: Field[]
}

/* Mixin uses many untyped dependencies */
/* eslint-disable @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,max-len,@typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-explicit-any */

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Mixin.register('generic-condition', {
    data(): {
        visibleValue: null|number,
        baseUnit: null|unknown,
        selectedUnit: null|unknown,
        } {
        return {
            visibleValue: null,
            baseUnit: null,
            selectedUnit: null,
        };
    },

    computed: {
        config(): Config {
            const config = Shopware.State.getters['ruleConditionsConfig/getConfigForType'](this.condition.type) as Config|undefined;

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
            let error: null|unknown = null;

            Object.values(this.config.fields).forEach((config) => {
                if (error) {
                    return;
                }

                const errorProperty = Shopware.State.getters['error/getApiError'](this.condition, `value.${config.name}`) as unknown;

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
                // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
                [`sw-condition__condition-type-${this.condition.type}`]: true,
            };
        },
    },

    methods: {
        getBind(field: Field) {
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

        updateFieldValue(fieldName: string, value: number, to = undefined, from = undefined) {
            if (!from || !to || from === to) {
                this.$set(this.values, fieldName, value);

                return;
            }

            this.$set(this.values, fieldName, convertUnit(value, {
                from,
                to,
            }));
        },

        updateVisibleValue(value: number) {
            this.visibleValue = value;
        },

        getVisibleValue(fieldName: string) {
            if (this.visibleValue === null) {
                // @ts-expect-error - value exists in main component
                return this.values[fieldName];
            }

            return this.visibleValue;
        },

        handleUnitChange(event: { unit: unknown, value: number }) {
            this.selectedUnit = event.unit;

            this.updateVisibleValue(event.value);
        },

        /**
         * @param event represents the base unit
         */
        setDefaultUnit(event: unknown) {
            this.baseUnit = event;
        },
    },
});
