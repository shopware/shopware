import template from './sw-cms-el-config-product-listing.html.twig';
import './sw-cms-el-config-product-listing.scss';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-cms-el-config-product-listing', {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            productSortings: [], // only for presentational usage
            defaultSorting: {}
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        productSortings: {
            handler() {
                this.element.config.availableSortings.value = this.transformProductSortings();
            },
            deep: true
        },

        defaultSorting() {
            if (Object.keys(this.defaultSorting).length === 0) {
                this.element.config.defaultSorting.value = '';
            } else {
                this.element.config.defaultSorting.value = this.defaultSorting.key;
            }
        }
    },

    computed: {
        showSortingGrid() {
            return this.element.config.useCustomSorting.value;
        },

        productSortingRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        productSortingsCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equalsAny('key', [...Object.keys(this.productSortingsConfigValue)]));
            criteria.addSorting(Criteria.sort('priority', 'desc'));

            return criteria;
        },

        allProductSortingsCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        excludedDefaultSortingCriteria() {
            const criteria = new Criteria();

            if (this.defaultSorting.id) {
                criteria.addFilter(Criteria.not(
                    'AND',
                    [Criteria.equals('id', this.defaultSorting.id)]
                ));
            }

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        productSortingsConfigValue() {
            return this.element.config.availableSortings.value;
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');

            if (Shopware.Utils.types.isEmpty(this.productSortingsConfigValue)) {
                this.productSortings = new EntityCollection(
                    this.productSortingRepository.route,
                    this.productSortingRepository.schema.entity,
                    Shopware.Context.api,
                    this.productSortingsCriteria
                );
            } else {
                this.fetchProductSortings().then(productSortings => {
                    this.productSortings = productSortings;
                });
            }

            this.initDefaultSorting();
        },

        fetchProductSortings() {
            return this.productSortingRepository.search(this.productSortingsCriteria, Shopware.Context.api)
                .then(productSortings => this.updateValuesFromConfig(productSortings));
        },

        updateValuesFromConfig(productSortings) {
            Object.entries(this.productSortingsConfigValue).forEach(([key, value]) => {
                const matchingProductSorting = productSortings.find(productSorting => productSorting.key === key);

                if (!matchingProductSorting) {
                    return;
                }

                matchingProductSorting.priority = value;
            });

            return productSortings;
        },

        /**
         * This functions transforms the product sorting entities to an format that the server accepts
         * e.g. 'Product sorting entity' => [{ 'test-sorting': 10 }]
         */
        transformProductSortings() {
            const object = {};

            this.productSortings.forEach(currentProductSorting => {
                object[currentProductSorting.key] = currentProductSorting.priority;
            });

            return object;
        },

        initDefaultSorting() {
            const defaultSortingKey = this.element.config.defaultSorting.value;
            if (defaultSortingKey !== '') {
                const criteria = new Criteria();

                criteria.addFilter(Criteria.equals('key', defaultSortingKey));

                this.productSortingRepository.search(criteria, Shopware.Context.api)
                    .then(response => {
                        this.defaultSorting = response.first();
                    });
            }
        },

        onDefaultSortingChange(entity, defaultSorting) {
            if (!defaultSorting) {
                this.defaultSorting = {};
                return;
            }

            // add the default sorting to available sortings, so it won't break logic
            if (!this.productSortings.has(defaultSorting.id)) {
                this.productSortings.add(defaultSorting);
            }

            this.defaultSorting = defaultSorting;
        },

        isDefaultSorting(productSorting) {
            return this.defaultSorting.key === productSorting.key;
        }
    }
});
