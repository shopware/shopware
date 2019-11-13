import CriteriaFactory from 'src/core/factory/criteria.factory';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-product-stream-filter.html.twig';
import TYPES from './type-provider';
import './sw-product-stream-filter.scss';

const { Component, Entity, Mixin, StateDeprecated } = Shopware;

const productDefinitionName = 'product';

Component.extend('sw-product-stream-filter', 'sw-condition-base', {
    template,

    inject: ['productStreamConditionService', 'entityAssociationStore', 'isApi'],
    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            fields: [],
            type: {},
            lastField: {},
            defaultPath: this.condition.field,
            multiValues: [],
            typeCriteria: null,
            fieldPath: [],
            negatedCondition: null,
            definitionBlacklist: {},
            filterValue: null
        };
    },

    computed: {
        fieldNames() {
            return ['type', 'field', 'operator', 'value', 'parameters', 'position', 'attributes'];
        },
        definitions() {
            return this.getDefinitions();
        },
        definition() {
            return this.definitions[this.definitions.length - 1];
        },
        actualCondition() {
            return this.negatedCondition || this.condition;
        },
        conditionFieldPath() {
            return this.actualCondition.field.split('.');
        },
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: '1'
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: '0'
                }
            ];

            return new LocalStore(values, 'value');
        },
        conditionClass() {
            return 'sw-product-stream-filter';
        },
        types() {
            return [
                {
                    type: TYPES.TYPE_RANGE,
                    name: this.$tc('sw-product-stream.filter.type.range')
                },
                {
                    type: TYPES.TYPE_EQUALS,
                    name: this.$tc('sw-product-stream.filter.type.equals')
                },
                {
                    type: TYPES.TYPE_CONTAINS,
                    name: this.$tc('sw-product-stream.filter.type.contains')
                },
                {
                    type: TYPES.TYPE_GREATER_THAN,
                    name: this.$tc('sw-product-stream.filter.type.greaterThan')
                },
                {
                    type: TYPES.TYPE_LESS_THAN,
                    name: this.$tc('sw-product-stream.filter.type.lessThan')
                },
                {
                    type: TYPES.TYPE_GREATER_THAN_EQUALS,
                    name: this.$tc('sw-product-stream.filter.type.greaterThanEquals')
                },
                {
                    type: TYPES.TYPE_LESS_THAN_EQUALS,
                    name: this.$tc('sw-product-stream.filter.type.lessThanEquals')
                },
                {
                    type: TYPES.TYPE_EQUALS_ANY,
                    name: this.$tc('sw-product-stream.filter.type.equalsAny')
                },
                {
                    type: TYPES.TYPE_NOT_EQUALS,
                    name: this.$tc('sw-product-stream.filter.type.notEquals'),
                    not: TYPES.TYPE_EQUALS
                },
                {
                    type: TYPES.TYPE_NOT_CONTAINS,
                    name: this.$tc('sw-product-stream.filter.type.notContains'),
                    not: TYPES.TYPE_CONTAINS
                },
                {
                    type: TYPES.TYPE_NOT_EQUALS_ANY,
                    name: this.$tc('sw-product-stream.filter.type.notEqualsAny'),
                    not: TYPES.TYPE_EQUALS_ANY
                }
            ];
        }
    },

    watch: {
        fields: {
            immediate: true,
            handler(newValue) {
                if (!newValue || this.fields.length === 0 || this.isApi()) {
                    return;
                }

                this.lastField = this.fields[this.fields.length - 1];

                const availableTypes = this.getAvailableTypes();

                const queries = availableTypes.map(type => CriteriaFactory.equals('type', type));
                this.typeCriteria = CriteriaFactory.multi('OR', ...queries);

                if (this.actualCondition.type && !availableTypes.includes(this.actualCondition.type)) {
                    this.actualCondition.type = availableTypes[0];
                    this.type = this.actualCondition.type;
                }

                if (this.actualCondition.field !== this.defaultPath) {
                    this.actualCondition.value = null;
                }
            }
        },
        'actualCondition.value': {
            immediate: true,
            handler() {
                this.mapValues();
            }
        },
        filterValue: {
            handler(newValue) {
                if (!newValue) {
                    return;
                }
                this.actualCondition.value = newValue.toString();
            }
        },
        condition() {
            this.negatedCondition = null;
            this.loadNegatedCondition();
            this.fields = this.getPathFields();

            this.type = this.findCorrectAbstractionForRangeType();

            this.filterValue = this.actualCondition.value;
        }
    },

    methods: {
        createdComponent() {
            this.locateConditionTreeComponent();

            if (this.isApi()) {
                return;
            }

            try {
                this.fields = this.getPathFields();
                this.lastField = this.fields[this.fields.length - 1];
            } catch (error) {
                this.conditionTreeComponent.isApi = true;
            }
        },
        mountedComponent() {
            this.loadNegatedCondition();

            this.type = this.findCorrectAbstractionForRangeType();

            this.filterValue = this.actualCondition.value;
            if (this.isApi()) {
                this.lastField = {};
            }
        },

        findCorrectAbstractionForRangeType() {
            if (this.type === TYPES.TYPE_RANGE) {
                const params = this.actualCondition.parameters;
                if (params.lt !== undefined && !params.gt && !params.lte && !params.gte) {
                    return TYPES.TYPE_LESS_THAN;
                }
                if (!params.lt && params.gt !== undefined && !params.lte && !params.gte) {
                    return TYPES.TYPE_GREATER_THAN;
                }
                if (!params.lt && !params.gt && params.lte !== undefined && !params.gte) {
                    return TYPES.TYPE_LESS_THAN_EQUALS;
                }
                if (!params.lt && !params.gt && !params.lte && params.gte !== undefined) {
                    return TYPES.TYPE_GREATER_THAN_EQUALS;
                }
            }
            return this.type;
        },

        getDefinitions() {
            if (this.isApi()) {
                return [];
            }

            this.definitionBlacklist = {};

            const definitions = [];
            const blackListedDefinitions = [];
            let definition = Object.assign({}, Entity.getDefinition(productDefinitionName));
            this.addDefinitionToStack(definition, definitions, blackListedDefinitions);

            this.fields.forEach((field) => {
                if (this.isEntityDefinition(field)) {
                    definition = Entity.getDefinition(field.entity);
                    this.addDefinitionToStack(definition, definitions, blackListedDefinitions);
                } else if (this.isObjectDefinition(field)) {
                    definition = field;
                    this.addDefinitionToStack(definition, definitions, blackListedDefinitions);
                }
            });

            return definitions;
        },
        addDefinitionToStack(definition, definitions, blackListedDefinitions) {
            blackListedDefinitions.push(definition.name);
            this.definitionBlacklist[definition.name] = blackListedDefinitions.slice(0);
            definitions.push({
                name: definition.name,
                type: definition.type,
                properties: this.filterProperties(definition)
            });
        },

        filterProperties(definition) {
            const store = {};
            Object.keys(definition.properties).forEach((key) => {
                if (this.isPropertyInAnyBlacklist(definition.name, key, definition.properties[key])) {
                    return;
                }

                store[key] = definition.properties[key];
                let label = '';
                if (key === 'id' && definition.name === productDefinitionName) {
                    label = this.$tc('sw-product-stream.filter.values.product');
                } else if (key === 'id') {
                    label = this.$tc('sw-product-stream.filter.values.choose');
                } else {
                    label = this.$tc(`sw-product-stream.filter.values.${key}`);
                }
                if (label === `sw-product-stream.filter.values.${key}` && store[key].label) {
                    label = store[key].label;
                }
                store[key].label = label;
                store[key].name = key;
                store[key].translated = {
                    label: store[key].label,
                    name: store[key].name
                };
            });
            return store;
        },

        isPropertyInAnyBlacklist(definitionName, property, field) {
            if (this.productStreamConditionService.isPropertyInBlacklist(definitionName, property)) {
                return true;
            }

            if (this.isEntityDefinition(field)) {
                property = field.entity;
            }

            return this.definitionBlacklist
                   && this.definitionBlacklist[definitionName]
                   && this.definitionBlacklist[definitionName].includes(property);
        },

        getDefinitionStore(definition) {
            return new LocalStore(Object.values(definition.properties), 'name');
        },
        getTypeStore() {
            return new LocalStore(this.types, 'type');
        },
        getStore(entity) {
            return StateDeprecated.getStore(entity);
        },

        getPathFields() {
            const fields = [];
            let definition = this.filterProperties(Entity.getDefinition(productDefinitionName));
            const productCustomFields = this.productStreamConditionService.productCustomFields;
            if (Object.keys(productCustomFields).length && !definition.customFields.properties) {
                definition.customFields.properties = productCustomFields;
            }
            if (!this.actualCondition.field) {
                this.actualCondition.field = 'id';
                fields.push(definition.id);
                return fields;
            }

            this.conditionFieldPath.forEach((path) => {
                const field = definition[path];
                // return if Element is product
                if (path === productDefinitionName) {
                    return;
                }

                if (!field || this.productStreamConditionService.isPropertyInBlacklist(definition.name, path)) {
                    throw new Error('field not found or in blacklist');
                }

                fields.push(field);

                if (this.isEntityDefinition(field)) {
                    definition = this.filterProperties(Entity.getDefinition(field.entity));
                    return;
                }

                if (this.isObjectDefinition(field)) {
                    definition = this.filterProperties(field);
                }
            });

            const latestField = fields[fields.length - 1];
            this.getLatestField(fields, latestField);

            const path = [];
            fields.forEach((field) => {
                path.push(field.name);
            });
            this.actualCondition.field = path.join('.');

            return fields;
        },

        getLatestField(fields, latestField) {
            let useAsObject = false;
            if (this.isEntityDefinition(latestField)) {
                const definition = Entity.getDefinition(latestField.entity);
                const additionField = this.filterProperties(definition);
                if (additionField.id) {
                    fields.push(additionField.id);
                    return fields;
                }
                useAsObject = true;
                latestField = definition;
            }

            if (this.isObjectDefinition(latestField) || useAsObject) {
                const definition = this.filterProperties(latestField);
                const firstProperty = Object.keys(definition)[0];
                fields.push(definition[firstProperty]);
                fields = this.getLatestField(fields, definition[firstProperty]);
            }

            return fields;
        },

        isObjectDefinition(field) {
            return field.type === 'object' && field.properties;
        },
        isEntityDefinition(field) {
            return !!field.entity;
        },

        loadNegatedCondition() {
            if (this.condition.type !== TYPES.TYPE_NOT) {
                this.type = this.condition.type;
                this.mapValues();
                return;
            }

            this.negatedCondition = this.condition.queries[0];

            this.type = this.types.find(type => type.not === this.negatedCondition.type).type;

            this.mapValues();
        },

        selectFilter(index, newValue) {
            this.multiValues = [];
            let path = this.conditionFieldPath;
            path = path.slice(0, index);
            path.push(newValue);
            this.actualCondition.field = path.join('.');
            this.fields = this.getPathFields();
        },

        mapValues() {
            if (!this.actualCondition.value) {
                return;
            }

            if (this.actualCondition.type === TYPES.TYPE_EQUALS_ANY) {
                this.multiValues = this.actualCondition.value.split('|');
            }
        },

        updateMultiValue(values) {
            this.actualCondition.value = values.map(value => value.id || value).join('|');
        },

        selectType(value) {
            if (!this.negatedCondition && this.isNegatedConditionType(value)) {
                this.createNegatedCondition();
            } else if (this.negatedCondition && !this.isNegatedConditionType(value)) {
                this.removeNegatedCondition();
            }

            this.actualCondition.type = this.getType(value);

            this.mapValues();
        },
        getType(newValue) {
            const rangeTypes = [
                TYPES.TYPE_LESS_THAN_EQUALS,
                TYPES.TYPE_LESS_THAN,
                TYPES.TYPE_GREATER_THAN_EQUALS,
                TYPES.TYPE_GREATER_THAN,
                TYPES.TYPE_RANGE
            ];

            if (rangeTypes.includes(newValue)) {
                if (this.actualCondition.type !== newValue) {
                    this.actualCondition.parameters = {};
                }

                this.mapParametersForType(newValue);
                return TYPES.TYPE_RANGE;
            }
            const types = this.types.find(type => type.type === newValue);

            return types.not || types.type;
        },

        isTypeRange() {
            return this.actualCondition.type === TYPES.TYPE_RANGE;
        },
        isTypeLower() {
            return this.type === TYPES.TYPE_LESS_THAN;
        },
        isTypeGreater() {
            return this.type === TYPES.TYPE_GREATER_THAN;
        },
        isTypeLowerEquals() {
            return this.type === TYPES.TYPE_LESS_THAN_EQUALS;
        },
        isTypeGreaterEquals() {
            return this.type === TYPES.TYPE_GREATER_THAN_EQUALS;
        },
        isDateTime() {
            return this.lastField.format === 'date-time';
        },

        mapParametersForType(type) {
            switch (type) {
                case TYPES.TYPE_LESS_THAN_EQUALS:
                    this.actualCondition.parameters = {
                        lt: undefined,
                        gt: undefined,
                        lte: 0,
                        gte: undefined
                    };
                    break;
                case TYPES.TYPE_LESS_THAN:
                    this.actualCondition.parameters = {
                        lt: 0,
                        gt: undefined,
                        lte: undefined,
                        gte: undefined
                    };
                    break;
                case TYPES.TYPE_GREATER_THAN_EQUALS:
                    this.actualCondition.parameters = {
                        lt: undefined,
                        gt: undefined,
                        lte: undefined,
                        gte: 0
                    };
                    break;
                case TYPES.TYPE_GREATER_THAN:
                    this.actualCondition.parameters = {
                        lt: undefined,
                        gt: 0,
                        lte: undefined,
                        gte: undefined
                    };
                    break;
                default:
                    this.actualCondition.parameters = {
                        lt: undefined,
                        gt: undefined,
                        lte: undefined,
                        gte: undefined
                    };
                    break;
            }
        },

        createNegatedCondition() {
            this.negatedCondition = this.entityAssociationStore().create();

            this.fieldNames.forEach(field => {
                this.negatedCondition[field] = this.condition[field];
                this.condition[field] = null;
            });

            this.condition.type = TYPES.TYPE_NOT;
            this.condition.queries = [this.negatedCondition];
            this.condition.position = this.negatedCondition.position;
            this.negatedCondition.parentId = this.condition.id;
        },
        removeNegatedCondition() {
            const parentId = this.condition.parentId;
            this.fieldNames.forEach(field => {
                this.condition[field] = this.negatedCondition[field];
                this.condition.original[field] = null;
            });

            this.condition.parentId = parentId;
            this.negatedCondition.delete();
            this.negatedCondition = null;
        },
        isNegatedConditionType(type) {
            return [
                TYPES.TYPE_NOT_EQUALS,
                TYPES.TYPE_NOT_EQUALS_ANY,
                TYPES.TYPE_NOT_CONTAINS
            ].includes(type);
        },

        updateTaggedValue(values) {
            this.actualCondition.value = values.map(value => value.id || value).join('|');
        },
        isEqualsAny(type) {
            return type === TYPES.TYPE_EQUALS_ANY;
        },
        isEquals(type) {
            return type === TYPES.TYPE_EQUALS;
        },
        getValueFieldByType(type) {
            switch (type) {
                case 'string':
                    if (this.isDateTime()) {
                        return 'date';
                    }
                    return 'text';
                case 'integer':
                case 'number':
                    this.filterValue = Number(this.actualCondition.value);
                    return 'number';
                default:
                    return type;
            }
        },
        getAvailableTypes() {
            if (!this.lastField) {
                return [TYPES.TYPE_EQUALS, TYPES.TYPE_NOT_EQUALS];
            }

            switch (this.lastField.type) {
                case 'boolean':
                    return [TYPES.TYPE_EQUALS];
                case 'string':
                    switch (this.lastField.format) {
                        case 'date-time':
                            return [
                                TYPES.TYPE_EQUALS,
                                TYPES.TYPE_GREATER_THAN,
                                TYPES.TYPE_GREATER_THAN_EQUALS,
                                TYPES.TYPE_LESS_THAN,
                                TYPES.TYPE_LESS_THAN_EQUALS,
                                TYPES.TYPE_NOT_EQUALS,
                                TYPES.TYPE_RANGE
                            ];
                        case 'uuid':
                            return [
                                TYPES.TYPE_EQUALS,
                                TYPES.TYPE_EQUALS_ANY,
                                TYPES.TYPE_NOT_EQUALS,
                                TYPES.TYPE_NOT_EQUALS_ANY
                            ];
                        default:
                            return [
                                TYPES.TYPE_EQUALS,
                                TYPES.TYPE_EQUALS_ANY,
                                TYPES.TYPE_CONTAINS,
                                TYPES.TYPE_NOT_EQUALS,
                                TYPES.TYPE_NOT_EQUALS_ANY,
                                TYPES.TYPE_NOT_CONTAINS
                            ];
                    }
                case 'integer':
                case 'number':
                case 'object':
                    return [
                        TYPES.TYPE_EQUALS,
                        TYPES.TYPE_GREATER_THAN,
                        TYPES.TYPE_GREATER_THAN_EQUALS,
                        TYPES.TYPE_LESS_THAN,
                        TYPES.TYPE_LESS_THAN_EQUALS,
                        TYPES.TYPE_NOT_EQUALS,
                        TYPES.TYPE_RANGE
                    ];
                default:
                    return [
                        TYPES.TYPE_EQUALS,
                        TYPES.TYPE_EQUALS_ANY,
                        TYPES.TYPE_NOT_EQUALS,
                        TYPES.TYPE_NOT_EQUALS_ANY
                    ];
            }
        }
    }
});
