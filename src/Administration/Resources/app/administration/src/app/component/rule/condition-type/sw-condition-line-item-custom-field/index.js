import template from './sw-condition-line-item-custom-field.html.twig';
import './sw-condition-line-item-custom-field.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;

/**
 * @package business-ops
 */
Component.extend('sw-condition-line-item-custom-field', 'sw-condition-base-line-item', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
    ],

    computed: {

        /**
         * Fetch custom fields that are related to the previously selected custom field set
         * @returns {Object.Criteria}
         */
        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customFieldSet');
            criteria.addFilter(Criteria.equals('customFieldSet.relations.entityName', 'product'));
            criteria.addSorting(Criteria.sort('customFieldSet.name', 'ASC'));
            return criteria;
        },

        operator: {
            get() {
                this.ensureValueExist();
                return this.condition.value.operator;
            },
            set(operator) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, operator };
            },
        },

        renderedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedField;
            },
            set(renderedField) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, renderedField };
            },
        },

        selectedField: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedField;
            },
            set(selectedField) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, selectedField };
            },
        },

        selectedFieldSet: {
            get() {
                this.ensureValueExist();
                return this.condition.value.selectedFieldSet;
            },
            set(selectedFieldSet) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, selectedFieldSet };
            },
        },

        renderedFieldValue: {
            get() {
                this.ensureValueExist();
                return this.condition.value.renderedFieldValue;
            },
            set(renderedFieldValue) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, renderedFieldValue };
            },
        },

        operators() {
            return this.conditionDataProviderService.getOperatorSetByComponent(this.renderedField);
        },

        ...mapPropertyErrors('condition', [
            'value.renderedField',
            'value.selectedField',
            'value.selectedFieldSet',
            'value.operator',
            'value.renderedFieldValue',
        ]),

        currentError() {
            return this.conditionValueRenderedFieldError
                || this.conditionValueSelectedFieldError
                || this.conditionValueSelectedFieldSetError
                || this.conditionValueOperatorError
                || this.conditionValueRenderedFieldValueError;
        },
    },

    methods: {
        getTooltipConfig(item) {
            if (item.allowCartExpose) {
                return { message: '', disabled: true };
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

        /**
         * Clear any further field's values if no custom field has been selected
         * @param id
         */
        onFieldChange(id) {
            if (this.$refs.selectedField.resultCollection.has(id)) {
                this.renderedField = this.$refs.selectedField.resultCollection.get(id);
                this.selectedFieldSet = this.renderedField.customFieldSetId;
            } else {
                this.renderedField = null;
            }

            this.operator = null;
            this.renderedFieldValue = null;
        },
    },
});
