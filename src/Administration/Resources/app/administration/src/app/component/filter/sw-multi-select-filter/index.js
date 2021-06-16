import template from './sw-multi-select-filter.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-multi-select-filter', {
    template,

    inject: ['repositoryFactory'],

    props: {
        filter: {
            type: Object,
            required: true,
        },
        active: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        isEntityMultiSelect() {
            return !this.filter.options;
        },

        labelProperty() {
            return this.filter.labelProperty || 'name';
        },

        values() {
            if (!this.isEntityMultiSelect) {
                return this.filter.value || [];
            }

            const entities = new EntityCollection(
                '',
                this.filter.schema.entity,
                Shopware.Context.api,
            );

            if (Array.isArray(this.filter.value)) {
                this.filter.value.forEach(value => {
                    entities.push({
                        id: value.id,
                        [this.labelProperty]: value[this.labelProperty],
                    });
                });
            }

            return entities;
        },
    },

    methods: {
        changeValue(newValues) {
            if (newValues.length <= 0) {
                this.resetFilter();
                return;
            }

            const filterCriteria = [
                this.filter.schema
                    ? Criteria.equalsAny(
                        `${this.filter.property}.${this.filter.schema.referenceField}`,
                        newValues.map(newValue => newValue[this.filter.schema.referenceField]),
                    )
                    : Criteria.equalsAny(this.filter.property, newValues),
            ];

            const values = !this.isEntityMultiSelect ? newValues : newValues.map(value => ({
                id: value.id,
                [this.labelProperty]: value[this.labelProperty],
            }));

            this.$emit('filter-update', this.filter.name, filterCriteria, values);
        },

        resetFilter() {
            this.$emit('filter-reset', this.filter.name);
        },
    },
});
