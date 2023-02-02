/**
 * @package sales-channel
 */

import template from './sw-sales-channel-product-assignment-categories.html.twig';
import './sw-sales-channel-product-assignment-categories.scss';

const { Component, Context, Mixin } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-sales-channel-product-assignment-categories', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        containerStyle: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            categories: [],
            searchResult: [],
            searchTerm: '',
            isFetching: false,
            isProductLoading: false,
            isComponentReady: false,
            categoriesCollection: [],
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        selectedCategoriesItemsIds() {
            return this.categoriesCollection.getIds();
        },

        selectedCategoriesPathIds() {
            return this.categoriesCollection.reduce((acc, item) => {
                // get each parent id
                const pathIds = item.path ? item.path.split('|').filter((pathId) => pathId.length > 0) : '';

                // add parent id to accumulator
                return [...acc, ...pathIds];
            }, []);
        },
    },

    watch: {
        categoriesCollection: {
            handler(value) {
                if (!value || !value.getIds().length) {
                    this.$emit('selection-change', [], 'categoryProducts');
                    return;
                }

                this.$emit('product-loading', true);
                this.isProductLoading = true;

                this.getProductFromCategories(value.getIds())
                    .then((products) => {
                        this.$emit('selection-change', products, 'categoryProducts');
                    })
                    .catch((error) => {
                        this.createNotificationError({ message: error.message });
                    })
                    .finally(() => {
                        this.$emit('product-loading', false);
                        this.isProductLoading = false;
                    });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange(input) {
            if (input.length <= 0) {
                return;
            }

            this.searchCategories(input).then((response) => {
                this.searchResult = response;
            });
        },
        createdComponent() {
            this.categoriesCollection = new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Context.api,
            );

            this.getTreeItems().then(() => {
                this.isComponentReady = true;
            });
        },

        categoryCriteria(parentId) {
            const categoryCriteria = new Criteria(1, 500);

            categoryCriteria.addFilter(
                Criteria.multi(
                    'AND',
                    [
                        Criteria.equals('parentId', parentId),
                        Criteria.multi(
                            'OR',
                            [
                                Criteria.equals('type', 'page'),
                                Criteria.equals('type', 'folder'),
                            ],
                        ),
                    ],
                ),
            );

            return categoryCriteria;
        },

        categorySearchCriteria(term) {
            const categorySearchCriteria = new Criteria(1, 500);
            categorySearchCriteria.addFilter(Criteria.equals('type', 'page'));
            categorySearchCriteria.setTerm(term);

            return categorySearchCriteria;
        },

        getTreeItems(parentId = null) {
            this.isFetching = true;

            // search for categories
            return this.categoryRepository.search(this.categoryCriteria(parentId), Context.api).then((searchResult) => {
                // when requesting root categories, replace the data
                if (parentId === null) {
                    this.categories = searchResult;
                    return Promise.resolve();
                }

                // add new categories
                searchResult.forEach((category) => {
                    this.categories.add(category);
                });

                return Promise.resolve();
            }).catch(err => {
                this.createNotificationError({
                    message: err.message,
                });
            }).finally(() => {
                this.isFetching = false;
            });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;
        },

        onCheckItem(item) {
            const itemIsInCategories = this.categoriesCollection.has(item.id);

            if (item.checked && !itemIsInCategories) {
                if (item.data) {
                    this.categoriesCollection.add(item.data);
                } else {
                    this.categoriesCollection.add(item);
                }

                return true;
            }

            this.removeItem(item);
            return false;
        },

        removeItem(item) {
            this.categoriesCollection.remove(item.id);
        },

        searchCategories(term) {
            return this.categoryRepository.search(this.categorySearchCriteria(term), Shopware.Context.api);
        },

        isSearchItemChecked(itemId) {
            if (this.selectedCategoriesItemsIds.length > 0) {
                return this.selectedCategoriesItemsIds.indexOf(itemId) >= 0;
            }
            return false;
        },

        onCheckSearchItem(item) {
            const shouldBeChecked = !this.isSearchItemChecked(item.id);

            this.onCheckItem({
                checked: shouldBeChecked,
                id: item.id,
                data: item,
            });
        },

        getBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated.name || item.name;
        },

        productCriteria(categories) {
            const productCriteria = new Criteria(1, 500);
            productCriteria.addFilter(
                Criteria.multi(
                    'AND',
                    [
                        Criteria.equalsAny('categoryIds', categories),
                        Criteria.equals('parentId', null),
                        Criteria.not('and', [
                            Criteria.equals('product.visibilities.salesChannelId', this.salesChannel.id),
                        ]),
                    ],
                ),
            );

            return productCriteria;
        },

        getProductFromCategories(categories) {
            return this.productRepository.search(this.productCriteria(categories), Shopware.Context.api);
        },
    },
});
