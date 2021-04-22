import template from './sw-condition-line-item-of-manufacturer.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

Component.extend('sw-condition-line-item-of-manufacturer', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            manufacturers: null,
            inputKey: 'manufacturerIds',
        };
    },

    computed: {
        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        manufacturerIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.manufacturerIds || [];
            },
            set(manufacturerIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, manufacturerIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.manufacturerIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueManufacturerIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.manufacturers = new EntityCollection(
                this.manufacturerRepository.route,
                this.manufacturerRepository.entityName,
                Context.api,
            );

            if (this.manufacturerIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.manufacturerIds);

            return this.manufacturerRepository.search(criteria, Context.api).then((manufacturers) => {
                this.manufacturers = manufacturers;
            });
        },

        setManufacturerIds(manufacturers) {
            this.manufacturerIds = manufacturers.getIds();
            this.manufacturers = manufacturers;
        },
    },
});
