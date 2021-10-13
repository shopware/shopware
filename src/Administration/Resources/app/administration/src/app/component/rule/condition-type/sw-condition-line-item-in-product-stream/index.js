import template from './sw-condition-line-item-in-product-stream.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

Component.extend('sw-condition-line-item-in-product-stream', 'sw-condition-base-line-item', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            streams: null,
            inputKey: 'streamIds',
        };
    },

    computed: {
        streamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        streamIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.streamIds || [];
            },
            set(streamIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, streamIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.streamIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueStreamIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.streams = new EntityCollection(
                this.streamRepository.route,
                this.streamRepository.entityName,
                Context.api,
            );

            if (this.streamIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.streamIds);

            return this.streamRepository.search(criteria, Context.api).then((streams) => {
                this.streams = streams;
            });
        },

        setStreamIds(streams) {
            this.streamIds = streams.getIds();
            this.streams = streams;
        },
    },
});
