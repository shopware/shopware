import { Component, Entity, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-product-stream-filter.html.twig';

Component.extend('sw-product-stream-filter', 'sw-condition-base', {
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification'),
        Mixin.getByName('condition')
    ],

    data() {
        return {
            fields: [],
            type: {},
            multiValues: [],
            operatorCriteria: {},
            fieldPath: []
        };
    },

    computed: {
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
        types() {
            return {
                range: { type: 'range', name: this.$tc('sw-product-stream.filter.type.range') },
                equals: { type: 'equals', name: this.$tc('sw-product-stream.filter.type.equals') },
                contains: { type: 'contains', name: this.$tc('sw-product-stream.filter.type.contains') },
                equalsAny: { type: 'equalsAny', name: this.$tc('sw-product-stream.filter.type.equalsAny') }
            };
        }
    },

    watch: {
        // TODO: will be changed by NEXT-1709
        fields(newValue) {
            const field = newValue[newValue.length - 1];
            switch (field.type) {
            case 'string':
                if (field.format === 'date-time') {
                    this.operatorCriteria = CriteriaFactory.equals('type', 'equals');
                    break;
                }

                this.operatorCriteria = CriteriaFactory.multi('OR',
                    CriteriaFactory.equals('type', 'contains'),
                    CriteriaFactory.equals('type', 'equals'),
                    CriteriaFactory.equals('type', 'equalsAny'));
                break;
            case 'integer':
            case 'object':
            case 'number':
                if (field.entity) {
                    break;
                }
                this.operatorCriteria = CriteriaFactory.multi('OR',
                    CriteriaFactory.equals('type', 'equals'),
                    CriteriaFactory.equals('type', 'equalsAny'),
                    CriteriaFactory.equals('type', 'range'));
                break;
            default:
                this.operatorCriteria = CriteriaFactory.multi('OR',
                    CriteriaFactory.equals('type', 'equals'),
                    CriteriaFactory.equals('type', 'equalsAny'));
            }

            this.condition.field = newValue
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
            return new LocalStore(Object.values(this.types), 'type');
        },
        createdComponent() {
            this.locateConditionTreeComponent();
            this.fields.push({ name: 'product', entity: 'product', type: 'object' });
            this.mapValues();
        },
        mountComponent() {
            this.buildFieldPath();
        },

        // TODO: will be changed by NEXT-1709
        buildFieldPath() {
            if (!this.condition || !this.condition.field) {
                return;
            }

            this.fieldPath = this.condition.field.split('.');
            let definition = Entity.getDefinition('product').properties;

            if (this.fieldPath[0] === 'product') {
                this.fieldPath = this.fieldPath.splice(0, 1);
            }

            for (let i = 0; i < this.fieldPath.length; i += 1) {
                this.fields.push(definition[this.fieldPath[i]]);
                if (definition.entity) {
                    definition = Entity.getDefinition(definition.entity).properties[this.fieldPath[i]];
                }
            }
        },
        mapValues() {
            if (!this.condition.value) {
                return;
            }

            if (this.condition.type === 'equalsAny') {
                this.multiValues = this.condition.value.split('|');
            }
        },
        selectFilter(index, newValue) {
            // TODO: will be changed by NEXT-1709
            for (let i = this.fields.length; i > index + 1; i -= 1) {
                this.fields.pop();
            }

            const newDefinition = this.definition.properties[newValue];

            this.fields.push(newDefinition);
        },
        selectType(value) {
            if (value && !this.types[value]) {
                return;
            }

            this.condition.type = this.types[value].type;

            this.mapValues();
        },
        updateTaggedValue(values) {
            this.condition.value = values.map(value => value.id || value).join('|');
        }
    }
});
