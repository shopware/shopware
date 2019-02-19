import { Component, Entity, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-product-stream-filter.html.twig';
import TYPES from './type-provider';
import './sw-product-stream-filter.scss';

Component.extend('sw-product-stream-filter', 'sw-condition-base', {
    template,

    inject: ['productStreamConditionService', 'entityAssociationStore'],
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
            value: null,
            typeCriteria: null,
            fieldPath: [],
            negatedCondition: null,
            isApi: false,
            definitionBlacklist: null
        };
    },

    computed: {
        fieldNames() {
            return ['type', 'field', 'operator', 'value', 'parameters', 'position', 'attributes'];
        },
        definitions() {
            if (this.isApi) {
                return [];
            }
            this.definitionBlacklist = {};

            const definitions = [];
            const blackListedDefinitions = [];
            let definition = Entity.getDefinition('product');
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

        definition() {
            return this.definitions[this.definitions.length - 1];
        },
        actualCondition() {
            return this.negatedCondition || this.condition;
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
                if (!newValue || this.fields.length === 0) {
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
        'condition.value': {
            immediate: true,
            handler() {
                this.mapValues();
            }
        },
        'conditionTreeComponent.isApi': {
            handler() {
                if (!this.conditionTreeComponent.isApi) {
                    return;
                }

                this.isApi = true;
                this.field = [];
                this.lastField = [];
            }
        }
    },

    methods: {
        getDefinitionStore(definition) {
            return new LocalStore(Object.values(definition.properties), 'name');
        },
        getTypeStore() {
            return new LocalStore(this.types, 'type');
        },
        getStore(entity) {
            return State.getStore(entity);
        },
        createdComponent() {
            this.locateConditionTreeComponent();

            if (this.conditionTreeComponent.isApi) {
                this.isApi = true;
                return;
            }

            try {
                this.fields = this.getPathFields();
                this.lastField = this.fields[this.fields.length - 1];
            } catch (error) {
                this.conditionTreeComponent.isApi = true;
                this.isApi = true;
            }
        },

        addDefinitionToStack(definition, definitions, blackListedDefinitions) {
            blackListedDefinitions.push(definition.name);
            this.definitionBlacklist[definition.name] = blackListedDefinitions.slice(0);
            definition.properties = this.filterProperties(definition);
            definitions.push(definition);
        },

        isObjectDefinition(field) {
            return field.type === 'object' && field.properties;
        },

        isEntityDefinition(field) {
            return !!field.entity;
        },

        mountComponent() {
            this.loadNegatedCondition();
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

        getPathFields() {
            const fields = [];
            let definition = this.filterProperties(Entity.getDefinition('product'));
            if (!this.actualCondition.field) {
                this.actualCondition.field = 'id';
                fields.push(definition.id);
                return fields;
            }

            this.actualCondition.field.split('.').forEach((path) => {
                const field = definition[path];
                // return if Element is product
                if (path === 'product') {
                    return;
                }

                if (!field
                    || (this.productStreamConditionService.blacklist[definition.name]
                        && this.productStreamConditionService.blacklist[definition.name].includes(path))) {
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

        filterProperties(definition) {
            const store = {};
            Object.keys(definition.properties).forEach((key) => {
                if ((this.productStreamConditionService.blacklist[definition.name]
                    && this.productStreamConditionService.blacklist[definition.name].includes(key))
                    || (this.definitionBlacklist
                        && this.definitionBlacklist[definition.name]
                        && this.definitionBlacklist[definition.name].includes(key))) {
                    return;
                }

                store[key] = definition.properties[key];
                let label = '';
                if (key === 'id' && definition.name === 'product') {
                    label = this.$tc('sw-product-stream.filter.values.product');
                } else if (key === 'id') {
                    label = this.$tc('sw-product-stream.filter.values.choose');
                } else {
                    label = this.$tc(`sw-product-stream.filter.values.${key}`);
                }

                store[key].label = label;
                store[key].name = key;
                store[key].meta = {
                    viewData: {
                        label: store[key].label,
                        name: store[key].name
                    }
                };
            });
            return store;
        },

        selectFilter(index, newValue) {
            let path = this.actualCondition.field.split('.');
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

        getValueFieldByType(type) {
            switch (type) {
            case 'string':
                return 'text';
            case 'integer':
                return 'number';
            default:
                return type;
            }
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
            const types = this.types.find(type => type.type === newValue);

            return types.not || types.type;
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
                    return [TYPES.TYPE_EQUALS, TYPES.TYPE_NOT_EQUALS];
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
                if (this.lastField.entity) {
                    return [
                        TYPES.TYPE_EQUALS,
                        TYPES.TYPE_EQUALS_ANY,
                        TYPES.TYPE_NOT_EQUALS,
                        TYPES.TYPE_NOT_EQUALS_ANY
                    ];
                }
                return [
                    TYPES.TYPE_EQUALS,
                    TYPES.TYPE_EQUALS_ANY,
                    TYPES.TYPE_NOT_EQUALS,
                    TYPES.TYPE_NOT_EQUALS_ANY,
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
