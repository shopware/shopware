import { Component, Entity, Mixin, State } from 'src/core/shopware';
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
        lastField() {
            return this.fields[this.fields.length - 1];
        },
        fieldPath() {
            return this.fields.map(field => field.name).join('.');
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
        fields() {
            let filter = '.*';
            const field = this.fields[this.fields.length - 1];
            if (field.type === 'string') {
                filter = '^(?!range).*';

                if (field.format === 'date-time') {
                    filter = '^equals(?!Any)';
                }
            } else if (field.type === 'integer') {
                filter = '^(?!contains).*';
            } else {
                filter = '^equals.*';
            }
            this.operatorCriteria = CriteriaFactory.contains('type', filter);
            this.condition.field = this.fields.map(fieldName => fieldName.name).join('.');
        }
    },

    data() {
        return {
            fields: [],
            type: {},
            multiValue: [],
            operatorCriteria: {}
        };
    },

    methods: {
        createComponent() {
            if (this.condition && this.condition.field) {
                this.fields = this.condition.field.split('.');
            }
        },
        mountComponent() {
        },
        getStore(entityName) {
            return State.getStore(entityName);
        },
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
            this.fields.push({ name: 'product', entity: 'product', type: 'object' });
        },
        selectFilter(index, newValue) {
            if (index + 1 < this.fields.length) {
                for (let i = this.fields.length; i > index + 1; i -= 1) {
                    this.fields.pop();
                }
            }

            const newDefinition = this.definition.properties[newValue];
            this.fields.push(newDefinition);
        },
        selectType(value) {
            if (value && !this.types[value]) {
                return;
            }

            this.condition.type = this.types[value].type;
        },
        updateTaggedValue(values) {
            this.condition.value = values.map(value => value.id).join('|');
        }
    }
});
