import template from './sw-cms-el-config-product-listing.html.twig';
import './sw-cms-el-config-product-listing.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @private
 * @package content
 */
export default {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            productSortings: [], // only for presentational usage
            defaultSorting: {},
            filters: [],
            filterPropertiesTerm: '',
            properties: [],
            propertiesPage: 1,
            propertiesLimit: 6,
            propertiesTotal: 0,
        };
    },

    computed: {
        showSortingGrid() {
            return this.element.config.useCustomSorting.value;
        },

        showFilterGrid() {
            return !this.filterByProperties;
        },

        productSortingRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group');
        },

        productSortingsCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('key', [...Object.keys(this.productSortingsConfigValue)]));
            criteria.addSorting(Criteria.sort('priority', 'desc'));

            return criteria;
        },

        propertyCriteria() {
            const criteria = new Criteria(this.propertiesPage, this.propertiesLimit);

            criteria.setTerm(this.filterPropertiesTerm);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.equals('filterable', true));

            return criteria;
        },

        allProductSortingsCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        excludedDefaultSortingCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.defaultSorting.id) {
                criteria.addFilter(Criteria.not(
                    'AND',
                    [Criteria.equals('id', this.defaultSorting.id)],
                ));
            }

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        productSortingsConfigValue() {
            return this.element.config.availableSortings.value;
        },

        filterByManufacturer: {
            get() {
                return this.isActiveFilter('manufacturer-filter');
            },
            set(value) {
                this.updateFilters('manufacturer-filter', value);
            },
        },

        filterByRating: {
            get() {
                return this.isActiveFilter('rating-filter');
            },
            set(value) {
                this.updateFilters('rating-filter', value);
            },
        },

        filterByPrice: {
            get() {
                return this.isActiveFilter('price-filter');
            },
            set(value) {
                this.updateFilters('price-filter', value);
            },
        },

        filterByFreeShipping: {
            get() {
                return this.isActiveFilter('shipping-free-filter');
            },
            set(value) {
                this.updateFilters('shipping-free-filter', value);
            },
        },

        filterByProperties: {
            get() {
                return !this.isActiveFilter('property-filter');
            },
            set(value) {
                this.updateFilters('property-filter', !value);
                this.sortProperties(this.properties);
            },
        },

        showPropertySelection() {
            return !this.properties.length < 1;
        },

        gridColumns() {
            return [
                {
                    property: 'status',
                    label: 'sw-cms.elements.productListing.config.filter.gridHeaderStatus',
                    disabled: this.showFilterGrid,
                    width: '70px',
                },
                {
                    property: 'name',
                    label: 'sw-cms.elements.productListing.config.filter.gridHeaderName',
                },
            ];
        },

        gridClasses() {
            return {
                'is--disabled': this.showFilterGrid,
            };
        },
    },

    watch: {
        productSortings: {
            handler() {
                this.element.config.availableSortings.value = this.transformProductSortings();
            },
            deep: true,
        },

        defaultSorting() {
            if (Object.keys(this.defaultSorting).length === 0) {
                this.element.config.defaultSorting.value = '';
            } else {
                this.element.config.defaultSorting.value = this.defaultSorting.key;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');

            if (Shopware.Utils.types.isEmpty(this.productSortingsConfigValue)) {
                this.productSortings = new EntityCollection(
                    this.productSortingRepository.route,
                    this.productSortingRepository.schema.entity,
                    Shopware.Context.api,
                    this.productSortingsCriteria,
                );
            } else {
                this.fetchProductSortings().then(productSortings => {
                    this.productSortings = productSortings;
                });
            }

            this.initDefaultSorting();
            this.unpackFilters();
            this.loadFilterableProperties();
        },

        fetchProductSortings() {
            return this.productSortingRepository.search(this.productSortingsCriteria)
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
                const criteria = new Criteria(1, 25);

                criteria.addFilter(Criteria.equals('key', defaultSortingKey));

                this.productSortingRepository.search(criteria)
                    .then(response => {
                        this.defaultSorting = response.first();
                    });
            }
        },

        loadFilterableProperties() {
            return this.propertyRepository.search(this.propertyCriteria)
                .then(properties => {
                    this.propertiesTotal = properties.total;

                    this.properties = this.sortProperties(properties);
                });
        },

        sortProperties(properties) {
            properties.forEach(property => {
                if (!this.filterByProperties) {
                    property.active = true;

                    return;
                }

                // eslint-disable-next-line inclusive-language/use-inclusive-words
                property.active = this.element.config.propertyWhitelist.value.includes(property.id);
            });

            properties.sort((a, b) => {
                if (a.active === b.active || !a.active === !b.active) {
                    return 0;
                }

                if (a.active) {
                    return -1;
                }

                return 1;
            });

            return properties;
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
        },

        isActiveFilter(item) {
            return this.filters.includes(item);
        },

        updateFilters(item, active) {
            if (active) {
                this.filters = [...this.filters, item];
            } else {
                this.filters = this.filters
                    .reduce((acc, current) => {
                        if (current === item) {
                            return acc;
                        }

                        return [...acc, current];
                    }, []);
            }

            this.element.config.filters.value = this.filters.join();
        },

        unpackFilters() {
            if (this.element.config.filters === undefined) {
                return;
            }

            const filters = this.element.config.filters.value;

            if (filters === null || filters === '') {
                return;
            }

            this.filters = filters.split(',');
        },

        onFilterProperties() {
            this.propertiesPage = 1;

            return this.loadFilterableProperties();
        },

        onPropertiesPageChange({ limit, page }) {
            this.propertiesLimit = limit;
            this.propertiesPage = page;

            return this.loadFilterableProperties();
        },

        propertyStatusChanged(id) {
            // eslint-disable-next-line inclusive-language/use-inclusive-words
            const allowlist = this.element.config.propertyWhitelist.value;

            if (!allowlist.includes(id)) {
                // eslint-disable-next-line inclusive-language/use-inclusive-words
                this.element.config.propertyWhitelist.value = [...allowlist, id];

                return;
            }

            // eslint-disable-next-line inclusive-language/use-inclusive-words
            this.element.config.propertyWhitelist.value = allowlist
                .reduce((acc, current) => {
                    if (current === id) {
                        return acc;
                    }

                    return [...acc, current];
                }, []);
        },
    },
};
