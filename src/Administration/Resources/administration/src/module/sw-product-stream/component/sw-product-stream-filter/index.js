import { Component, Entity, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-product-stream-filter.html.twig';
import TYPES from './type-provider';

Component.extend('sw-product-stream-filter', 'sw-condition-base', {
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            fields: [],
            type: {},
            multiValues: [],
            value: null,
            typeCriteria: null,
            fieldPath: [],
            negatedCondition: null
        };
    },

    computed: {
        fieldNames() {
            return ['type', 'field', 'operator', 'value', 'parameters', 'position', 'attributes'];
        },
        definitions() {
            const definitions = [];
            this.fields.forEach((field) => {
                if (field.entity) {
                    definitions.push(Entity.getDefinition(field.entity));
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
        // TODO: will be changed by NEXT-1709
        fields(newValue) {
            const field = newValue[newValue.length - 1];

            const availableTypes = this.getAvailableTypes(field);

            const queries = availableTypes.map(type => CriteriaFactory.equals('type', type));
            this.typeCriteria = CriteriaFactory.multi('OR', ...queries);

            if (this.actualCondition.type && !availableTypes.includes(this.actualCondition.type)) {
                this.actualCondition.type = availableTypes[0];
            }

            this.actualCondition.field = newValue
                .filter((fieldObject, index) => !(index === 0 && fieldObject.name === 'product'))
                .map(fieldObject => fieldObject.name)
                .join('.');
        }
    },

    methods: {
        getDefinitionStore(definition) {
            Object.keys(definition.properties).forEach((key) => {
                definition.properties[key].name = key;
            });

            return new LocalStore(Object.values(definition.properties), 'name');
        },
        getTypeStore() {
            return new LocalStore(this.types, 'type');
        },
        createdComponent() {
            this.typeStore = new LocalStore(Object.values(this.types), 'type');
            this.locateConditionTreeComponent();
            this.fields.push({ name: 'product', entity: 'product', type: 'object' });
            this.mapValues();
        },
        mountComponent() {
            this.loadNegatedCondition();
            this.buildFieldPath();
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

        buildFieldPath() {
            if (!this.actualCondition || !this.actualCondition.field) {
                return;
            }

            this.fieldPath = this.actualCondition.field.split('.');
            let definition = Entity.getDefinition('product').properties;

            if (this.fieldPath[0] === 'product') {
                this.fieldPath.shift();
            }

            this.fieldPath.forEach(field => {
                this.fields.push(definition[field]);

                if (definition[field].entity) {
                    definition = Entity.getDefinition(definition[field].entity).properties;
                }
            });
        },
        mapValues() {
            if (!this.actualCondition.value) {
                return;
            }

            if (this.actualCondition.type === TYPES.TYPE_EQUALS_ANY) {
                this.multiValues = this.actualCondition.value.split('|');
            }
        },
        selectFilter(index, newValue) {
            // TODO: will be changed by NEXT-1709
            if (this.fields.length > index + 1) {
                this.fields.splice(index + 1);
            }

            const newDefinition = this.definition.properties[newValue];

            this.fields.push(newDefinition);
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
            this.negatedCondition = this.entityAssociationStore.create();

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
        getAvailableTypes(field) {
            if (!field) {
                return [TYPES.TYPE_EQUALS, TYPES.TYPE_NOT_EQUALS];
            }

            switch (field.type) {
            case 'string':
                switch (field.format) {
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
                if (field.entity) {
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
