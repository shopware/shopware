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
            const values = [
                { type: 'range', name: this.$tc('sw-product-stream.filter.type.range') },
                { type: 'equals', name: this.$tc('sw-product-stream.filter.type.equals') },
                { type: 'contains', name: this.$tc('sw-product-stream.filter.type.contains') },
                { type: 'equalsAny', name: this.$tc('sw-product-stream.filter.type.equalsAny') }
            ];

            const types = {};
            values.forEach(value => {
                types[value.type] = value;
            });

            return types;
        }
    },

    watch: {
        // TODO: will be changed by NEXT-1709
        fields(newValue) {
            let filter = '.*';
            const field = newValue[newValue.length - 1];
            switch (field.type) {
            case 'string':
                filter = '^(?!range).*';

                if (field.format === 'date-time') {
                    filter = '^equals(?!Any)';
                }
                break;
            case 'number':
                filter = '^(?!contains).*';
                break;
            default:
                filter = '^equals.*';
            }

            this.operatorCriteria = CriteriaFactory.contains('type', filter);
            this.condition.field = newValue
                .filter((fieldObject, index) => !(index === 0 && fieldObject.name === 'product'))
                .map(fieldObject => fieldObject.name)
                .join('.');
        }
    },

    data() {
        return {
            fields: [],
            type: {},
            multiValues: [],
            operatorCriteria: {},
            fieldPath: []
        };
    },

    methods: {
        getDefinitionStore(definition) {
            Object.keys(definition.properties).forEach((key) => {
                definition.properties[key].name = key;
            });

            return new LocalStore(definition.properties, 'name');
        },
        getTypeStore() {
            return new LocalStore(this.types, 'type');
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
