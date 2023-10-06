/*
 * @package business-ops
 */

import template from './sw-product-stream-value.html.twig';
import './sw-product-stream-value.scss';

const { Criteria } = Shopware.Data;
/*
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'conditionDataProviderService',
        'productCustomFields',
        'acl',
        'feature',
    ],

    props: {
        condition: {
            type: Object,
            required: true,
        },

        fieldName: {
            type: String,
            required: false,
            default: null,
        },

        definition: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            value: null,
            childComponents: null,
            searchTerm: '',
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create(this.definition.entity);
        },

        componentClasses() {
            return [
                this.growthClass,
                this.disabledClass,
            ];
        },

        growthClass() {
            if (this.childComponents === null) {
                return 'sw-product-stream-value--grow-0';
            }

            return `sw-product-stream-value--grow-${this.childComponents.length}`;
        },

        disabledClass() {
            return this.disabled ? 'is--disabled' : null;
        },

        actualCondition() {
            if (this.condition.type === 'not') {
                return this.condition.queries[0];
            }
            return this.condition;
        },

        isMultiSelectValue() {
            return this.actualCondition.type === 'equalsAny' ||
                this.actualCondition.type === 'equalsAll' ||
                this.actualCondition.type === 'notEqualsAll';
        },

        filterType: {
            get() {
                const conditionType = this.getConditionType(this.condition);
                if (conditionType === 'range') {
                    return this.getRangeType(this.actualCondition);
                }

                return conditionType;
            },

            set(type) {
                if (this.conditionDataProviderService.isRangeType(type)) {
                    this.onChangeType('range', this.getParameters(type));
                    return;
                }
                if (this.conditionDataProviderService.isRelativeTimeType(type)) {
                    this.onChangeType(type, this.getParameters(type));
                    return;
                }

                this.onChangeType(type, null);
            },
        },

        fieldDefinition() {
            const fieldDefinition = this.definition.getField(this.fieldName);

            if (!fieldDefinition && this.definition.entity === 'product' && this.fieldName) {
                if (this.conditionDataProviderService.allowedJsonAccessors.hasOwnProperty(this.fieldName)) {
                    return this.conditionDataProviderService.allowedJsonAccessors[this.fieldName];
                }

                return this.productCustomFields[this.fieldName.replace('customFields.', '')] || null;
            }

            return fieldDefinition;
        },

        operators() {
            if (this.fieldType === null) {
                return [];
            }
            return this.conditionDataProviderService
                .getOperatorSet(this.fieldType)
                .map((operator) => {
                    return {
                        label: this.$tc(operator.label),
                        value: operator.identifier,
                    };
                });
        },

        relativeTimeOperators() {
            const secondLevelOperators = this.conditionDataProviderService.getOperator(this.filterType).operators;

            return secondLevelOperators.map((operator) => {
                const secondLevelOperator = this.conditionDataProviderService.getOperator(operator);

                return {
                    label: this.$tc(secondLevelOperator.label),
                    value: secondLevelOperator.identifier,
                };
            });
        },

        productStateOptions() {
            return [
                { label: this.$tc('sw-product-stream.filter.values.productStates.physical'), value: 'is-physical' },
                { label: this.$tc('sw-product-stream.filter.values.productStates.digital'), value: 'is-download' },
            ];
        },

        fieldType() {
            if (!this.fieldDefinition) {
                return null;
            }

            if (this.fieldDefinition.type === 'json_list' && this.fieldName === 'states') {
                return 'product_state_list';
            }

            if (this.definition.isJsonField(this.fieldDefinition)) {
                return 'object';
            }

            if (this.fieldDefinition.type === 'uuid') {
                const isManyToOneFkField = Object.keys(this.definition.filterProperties((field) => {
                    return field.localField === this.fieldName && field.relation === 'many_to_one';
                })).length > 0;

                if (isManyToOneFkField) {
                    return 'empty';
                }
            }

            return this.fieldDefinition.type;
        },

        booleanOptions() {
            return [
                { label: this.$tc('global.default.yes'), value: '1' },
                { label: this.$tc('global.default.no'), value: '0' },
            ];
        },

        reversedEmptyOptions() {
            return [
                { label: this.$tc('global.default.yes'), value: false },
                { label: this.$tc('global.default.no'), value: true },
            ];
        },

        multiValue: {
            get() {
                if (typeof this.actualCondition.value !== 'string' || this.actualCondition.value === '') {
                    return [];
                }
                return this.actualCondition.value.split('|');
            },
            set(values) {
                this.actualCondition.value = values.join('|');
            },
        },

        inputComponent() {
            switch (this.fieldType) {
                case 'uuid':
                    return 'sw-entity-multi-id-select';
                case 'float':
                case 'int':
                    return 'sw-number-field';
                case 'date':
                    return 'sw-datepicker';
                case 'string':
                case 'object':
                default:
                    return 'sw-text-field';
            }
        },

        currentParameter: {
            get() {
                if (!this.actualCondition.parameters) {
                    return null;
                }
                return this.actualCondition.parameters[this.getParameterName(this.filterType)];
            },
            set(value) {
                const param = this.getParameterName(this.filterType);
                this.actualCondition.parameters = { [param]: value };
            },
        },

        gte: {
            get() { return this.actualCondition.parameters ? this.actualCondition.parameters.gte : null; },
            set(value) { this.actualCondition.parameters.gte = value; },
        },

        lte: {
            get() { return this.actualCondition.parameters ? this.actualCondition.parameters.lte : null; },
            set(value) { this.actualCondition.parameters.lte = value; },
        },

        operator: {
            get() {
                return this.actualCondition.parameters ?
                    this.getParameterType(this.actualCondition.parameters.operator) :
                    null;
            },
            set(value) { this.actualCondition.parameters.operator = this.getParameterName(value); },
        },

        emptyValue: {
            get() {
                return this.condition.type !== null ? this.filterType === 'equals' : null;
            },
            set(value) {
                if (value === undefined || value === null) {
                    this.$emit('empty-change', { type: null });

                    return;
                }

                this.$emit('empty-change', { type: value ? 'equals' : 'notEquals' });
            },
        },

        stringValue: {
            get() {
                if (['int', 'float'].includes(this.fieldType)) {
                    return Number.parseFloat(this.actualCondition.value);
                }
                if (typeof this.actualCondition.value !== 'string') {
                    return null;
                }
                if (this.conditionDataProviderService.isRelativeTimeType(this.filterType) && this.actualCondition.value) {
                    return this.actualCondition.value.match(/\d+/)[0];
                }
                return this.actualCondition.value;
            },
            set(value) {
                if (this.conditionDataProviderService.isRelativeTimeType(this.filterType)) {
                    this.actualCondition.value = `P${value}D`;
                    return;
                }
                this.actualCondition.value = value.toString();
            },
        },

        context() {
            return { ...Shopware.Context.api, inheritance: true };
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        propertyCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.definition.entity === 'property_group_option') {
                criteria.addAssociation('group');

                if (typeof this.searchTerm === 'string' && this.searchTerm.length > 0) {
                    criteria.addQuery(Criteria.contains('group.name', this.searchTerm), 500);
                }
            }

            return criteria;
        },

        visibilitiesCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('product');

            if (typeof this.searchTerm === 'string' && this.searchTerm.length > 0) {
                criteria.addQuery(Criteria.contains('salesChannel.name', this.searchTerm), 400);
                criteria.addQuery(Criteria.contains('product.name', this.searchTerm), 500);
            }

            return criteria;
        },

        resultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        visibilitiesLabelCallback() {
            return (item) => {
                if (!item) {
                    return '';
                }

                if (!item.salesChannel || !item.product) {
                    return item.id;
                }

                return `${item.salesChannel.translated.name}: ${item.product.translated.name}`;
            };
        },
    },

    mounted() {
        this.childComponents = this.$children;
    },

    methods: {
        onChangeType(type, parameters) {
            this.$emit('type-change', { type, parameters });
        },

        getConditionType(condition) {
            if (this.condition.type === 'not') {
                const innerType = condition.queries[0].type;
                const type = this.conditionDataProviderService.negateOperator(innerType);
                return type.identifier;
            }

            return this.condition.type;
        },

        getRangeType(condition) {
            if (condition.parameters === null) {
                return null;
            }

            const hasLte = condition.parameters.hasOwnProperty('lte');
            const hasGte = condition.parameters.hasOwnProperty('gte');

            if (hasGte && hasLte) {
                return this.conditionDataProviderService.getOperator('range').identifier;
            }

            if (hasGte) {
                return this.conditionDataProviderService.getOperator('greaterThanEquals').identifier;
            }

            if (hasLte) {
                return this.conditionDataProviderService.getOperator('lessThanEquals').identifier;
            }

            if (this.condition.parameters.hasOwnProperty('lt')) {
                return this.conditionDataProviderService.getOperator('lessThan').identifier;
            }

            if (this.condition.parameters.hasOwnProperty('gt')) {
                return this.conditionDataProviderService.getOperator('greaterThan').identifier;
            }

            return null;
        },

        getParameters(type) {
            if (type === 'range') {
                return { lte: null, gte: null };
            }

            if (type === 'since' || type === 'until') {
                return { operator: null };
            }

            const param = this.getParameterName(type);
            return param ? { [param]: null } : null;
        },

        getParameterName(type) {
            switch (type) {
                case 'greaterThanEquals':
                    return 'gte';
                case 'lessThanEquals':
                    return 'lte';
                case 'lessThan':
                    return 'lt';
                case 'greaterThan':
                    return 'gt';
                case 'equals':
                    return 'eq';
                case 'notEquals':
                    return 'neq';
                default:
                    return null;
            }
        },

        getParameterType(name) {
            switch (name) {
                case 'gte':
                    return 'greaterThanEquals';
                case 'lte':
                    return 'lessThanEquals';
                case 'lt':
                    return 'lessThan';
                case 'gt':
                    return 'greaterThan';
                case 'eq':
                    return 'equals';
                case 'neq':
                    return 'notEquals';
                default:
                    return null;
            }
        },

        setBooleanValue(value) {
            this.$emit('boolean-change', { type: +value ? 'equals' : 'notEquals', value });
        },

        setSearchTerm(value) {
            this.searchTerm = value;
        },

        onSelectCollapsed() {
            this.searchTerm = '';
        },

        getCategoryBreadcrumb(category) {
            if (!category.breadcrumb || Object.keys(category.breadcrumb).length === 0) {
                return category.name;
            }

            return Object.values(category.breadcrumb).join(' / ');
        },
    },
};
