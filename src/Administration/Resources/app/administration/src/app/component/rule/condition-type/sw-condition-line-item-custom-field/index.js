import template from './sw-condition-line-item-custom-field.html.twig';
import './sw-condition-line-item-custom-field.scss';

const { Component, Filter, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    computed: {
        customFieldCriteria() {
            return new Criteria(1, 25)
                .addAssociation('customFieldSet')
                .addFilter(Criteria.equals('customFieldSet.relations.entityName', 'product'))
                .addSorting(Criteria.sort('customFieldSet.name', 'ASC'));
        },

        operator: {
            get() {
                this.ensureValueExist();
                return this.condition.value.operator;
            },
            set(operator) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    operator,
                };
            },
        },

        renderedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedField;
            },
            set(renderedField) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    renderedField,
                };
            },
        },

        selectedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedField;
            },
            set(selectedField) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    selectedField,
                };
            },
        },

        selectedFieldSet: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedFieldSet;
            },
            set(selectedFieldSet) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    selectedFieldSet,
                };
            },
        },

        renderedFieldValue: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedFieldValue;
            },
            set(renderedFieldValue) {
                this.ensureValueExist();
                this.condition.value = {
                    ...this.condition.value,
                    renderedFieldValue,
                };
            },
        },

        operators() {
            return this.conditionDataProviderService.getOperatorSetByComponent(this.renderedField);
        },

        currentError() {
            return (
                this.conditionValueRenderedFieldError ||
                this.conditionValueSelectedFieldError ||
                this.conditionValueSelectedFieldSetError ||
                this.conditionValueOperatorError ||
                this.conditionValueRenderedFieldValueError
            );
        },

        truncateFilter() {
            return Filter.getByName('truncate');
        },

        ...mapPropertyErrors('condition', [
            'value.renderedField',
            'value.selectedField',
            'value.selectedFieldSet',
            'value.operator',
            'value.renderedFieldValue',
        ]),
    },

    methods: {
        getTooltipConfig(item) {
            if (item.allowCartExpose) {
                return {
                    message: '',
                    disabled: true,
                };
            }

            const route = {
                name: 'sw.settings.custom.field.detail',
                params: { id: item.customFieldSetId },
            };

            const routeData = this.$router.resolve(route);

            return {
                disabled: false,
                width: 260,
                message: this.$t('global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.tooltip', {
                    customFieldSettingsLink: routeData.href,
                }),
            };
        },

        getFieldDescription(item) {
            return this.getInlineSnippet(item.customFieldSet.config.label) || item.customFieldSet.name;
        },

        onFieldChange(id) {
            if (!this.$refs.selectedField.resultCollection?.has(id)) {
                this.operator = null;
                this.renderedFieldValue = null;
                this.renderedField = null;
                this.selectedFieldSet = null;
                return;
            }

            this.operator = null;
            this.renderedFieldValue = null;
            this.renderedField = this.$refs.selectedField.resultCollection.get(id);
            this.selectedFieldSet = this.renderedField.customFieldSetId;
        },
    },
};
