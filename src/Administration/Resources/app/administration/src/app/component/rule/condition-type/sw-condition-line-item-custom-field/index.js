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

    inject: ['repositoryFactory', 'feature'],

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

        /**
         * Only fetch custom field sets that are related to product use context
         * @major-deprecated tag:v6.5.0 - The computed property "customFieldSetCriteria"
         * will be removed because the set is fetched by association of the field instead
         * @returns {Object.Criteria}
         */
        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));
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

        /**
         * Clear any further field's value if custom field set selection has changed
         * @major-deprecated tag:v6.5.0 - The method "onFieldSetChange"
         * will be removed because the set will instead be determined by the selected field
         */
        onFieldSetChange() {
            this.selectedField = null;
            this.operator = null;
            this.renderedField = null;
        },
    },
});
