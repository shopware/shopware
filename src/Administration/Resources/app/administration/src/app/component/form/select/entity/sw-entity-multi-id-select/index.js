/**
 * @sw-package framework
 */

import template from './sw-entity-multi-id-select.html.twig';

const { Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { get } = Shopware.Utils;

/**
 * @private
 */
export default {
    template,

    inheritAttrs: false,

    inject: ['feature'],

    emits: ['update:value'],

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        value: {
            type: [
                Array,
                null,
            ],
            required: false,
            default: null,
        },

        repository: {
            type: Object,
            required: true,
        },

        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, 25);
            },
        },

        context: {
            type: Object,
            required: false,
            default() {
                return Context.api;
            },
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            collection: null,
        };
    },

    watch: {
        normalizedValue(value) {
            if (this.collection === null) {
                this.createdComponent();
                return;
            }

            if (Shopware.Utils.types.isEqual(this.collection.getIds(), value)) {
                return;
            }

            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    computed: {
        normalizedValue() {
            return this.value ?? [];
        },

        displayVariants() {
            return this.repository.entityName === 'product';
        },

        selectCriteria() {
            const criteria = Criteria.fromCriteria(this.criteria);

            if (this.displayVariants) {
                criteria.addAssociation('options.group');
            }

            return criteria;
        },
    },

    methods: {
        // note: this method also gets called when `value` updates
        createdComponent() {
            const collection = new EntityCollection(this.repository.route, this.repository.entityName, this.context);

            if (this.collection === null) {
                this.collection = collection;
            }

            if (this.normalizedValue.length === 0) {
                this.collection = collection;
                return Promise.resolve(this.collection);
            }

            const criteria = Criteria.fromCriteria(this.selectCriteria);
            criteria.setIds(this.normalizedValue);
            criteria.setTerm('');
            criteria.queries = [];

            return this.repository.search(criteria, { ...this.context, inheritance: true }).then((entities) => {
                this.collection = entities;

                if (!this.collection.length && this.normalizedValue.length) {
                    this.updateIds(this.collection);
                }

                return this.collection;
            });
        },

        updateIds(collection) {
            this.collection = collection;

            this.$emit('update:value', collection.getIds());
        },

        displayLabelProperty(item, labelProperty, getKey = get) {
            const labelProperties = Array.isArray(labelProperty) ? labelProperty : [labelProperty];

            return labelProperties
                .map((property) => getKey(item, property) || getKey(item, `translated.${property}`))
                .join(' ');
        },
    },
};
