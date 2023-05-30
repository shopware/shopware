import template from './sw-entity-multi-id-select.html.twig';

const { Component, Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-entity-multi-id-select', {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'ids',
        event: 'change',
    },

    props: {
        ids: {
            type: Array,
            required: false,
            default() {
                return [];
            },
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

    computed: {
        getListeners() {
            const listeners = {};

            Object.keys(this.$listeners).forEach(listener => {
                if (listener !== 'change') {
                    listeners[listener] = this.$listeners[listener];
                }
            });

            return listeners;
        },
    },

    watch: {
        ids() {
            if (this.collection === null) {
                this.createdComponent();
                return;
            }

            if (this.collection.getIds() === this.ids) {
                return;
            }

            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const collection = new EntityCollection(
                this.repository.route,
                this.repository.entityName,
                this.context,
            );

            if (this.collection === null) {
                this.collection = collection;
            }

            if (this.ids.length <= 0) {
                this.collection = collection;
                return Promise.resolve(this.collection);
            }

            const criteria = Criteria.fromCriteria(this.criteria);
            criteria.setIds(this.ids);
            criteria.setTerm('');
            criteria.queries = [];

            return this.repository.search(criteria, { ...this.context, inheritance: true }).then((entities) => {
                this.collection = entities;

                if (!this.collection.length && this.ids.length) {
                    this.updateIds(this.collection);
                }

                return this.collection;
            });
        },

        updateIds(collection) {
            this.collection = collection;
            this.$emit('change', collection.getIds());
        },
    },
});
