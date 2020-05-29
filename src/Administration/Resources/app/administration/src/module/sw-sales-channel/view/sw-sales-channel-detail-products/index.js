import template from './sw-sales-channel-detail-products.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';
import './sw-sales-channel-detail-products.scss';

const { Component, Mixin, Service, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-detail-products', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        salesChannel: {
            validator: (salesChannel) => {
                return typeof salesChannel === 'object';
            },
            required: true,
            default: null
        },

        productExport: {
            type: Object,
            required: true,
            default: null
        },

        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    data() {
        return {
            isLoadingCategories: true,
            loadedParentIds: [],
            categories: [],
            category: null
        };
    },

    computed: {
        ...mapGetters('swSalesChannel', [
            'needToCompleteTheSetup'
        ]),

        categoryRepository() {
            return Service('repositoryFactory').create('category');
        },

        categoriesCriteria() {
            const criteria = new Criteria();

            criteria.limit = 500;
            criteria.addFilter(Criteria.equals('parentId', null));

            return criteria;
        },

        categoryCriteria() {
            const criteria = new Criteria();

            criteria
                .getAssociation('seoUrls')
                .addFilter(Criteria.equals('isCanonical', true));

            criteria
                .addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels');

            return criteria;
        },

        searchCriteria() {
            const criteria = (new Criteria(1, 1))
                .addAssociation('children');

            criteria
                .getAssociation('children')
                .setLimit(500);

            return criteria;
        },

        categoryId: {
            get() {
                return this.$route.params.categoryId;
            },

            set(categoryId) {
                this.$route.params.categoryId = categoryId;
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.needToCompleteTheSetup.length) {
                this.$router.push({ name: 'sw.sales.channel.detail.base' });
            }

            this.loadCategoryData();
        },

        loadCategoryData() {
            if (this.categoryId) {
                this.loadActiveCategory().then(() => {
                    this.openInitialTree();
                });
            } else {
                this.loadRootCategories().then(() => {
                    this.isLoadingCategories = false;
                });
            }
        },

        loadActiveCategory() {
            return this.categoryRepository.get(this.categoryId, Shopware.Context.api, this.categoryCriteria).then((category) => {
                if (!category) {
                    this.loadRootCategories().then(() => {
                        this.$router.push({ name: 'sw.sales.channel.detail.products' });
                    });

                    return;
                }

                this.category = category;
            }).catch((error) => {
                const message = getErrorMessage(error) || this.$tc('global.default.notification.notificationFetchErrorMessage');

                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message
                });
            });
        },

        loadRootCategories() {
            return this.categoryRepository.search(this.categoriesCriteria, Shopware.Context.api).then((categories) => {
                this.addCategories(categories);
            });
        },

        async openInitialTree() {
            this.isLoadingCategories = true;
            this.categories = [];
            this.loadedParentIds = [];

            await this.loadRootCategories();

            if (!this.category || this.category.path === null) {
                this.isLoadingCategories = false;

                return Promise.resolve();
            }

            const parentIds = this.category.path.split('|').filter((parentId) => {
                return !!parentId;
            });

            const parentPromises = [];

            parentIds.forEach((parentId) => {
                const promise = this.categoryRepository.get(parentId, Shopware.Context.api, this.searchCriteria).then((result) => {
                    this.addCategories([result, ...result.children]);
                });

                parentPromises.push(promise);
            });

            return Promise.all(parentPromises).then(() => {
                this.isLoadingCategories = false;
            });
        },

        addCategories(categories) {
            if (!categories) {
                return;
            }

            categories.forEach((category) => {
                category.mapped = false;
            });

            this.categories = Utils.array.uniqBy([...this.categories, ...categories], 'id');
        },

        onChangeRoute(category) {
            this.category = category;

            this.$router.push({
                name: 'sw.sales.channel.detail.products.category',
                params: { categoryId: category.id }
            });
        },

        childrenCategoriesCriteria(parentId) {
            const criteria = new Criteria(1, 500);

            criteria.addFilter(Criteria.equals('parentId', parentId));

            return criteria;
        },

        onGetTreeItems(parentId) {
            if (this.loadedParentIds.includes(parentId)) {
                return Promise.resolve();
            }

            this.loadedParentIds.push(parentId);

            return this.categoryRepository.search(this.childrenCategoriesCriteria(parentId), Shopware.Context.api).then((categories) => {
                this.addCategories(categories);
            }).catch(() => {
                this.loadedParentIds = this.loadedParentIds.filter((loadedParentId) => {
                    return loadedParentId !== parentId;
                });
            });
        },

        onChangeGoogleCategory({ selectedShopCategory, selectedGoogleCategory }) {
            this.categories.forEach((category) => {
                if (category.id === selectedShopCategory.id) {
                    category.mapped = !!selectedGoogleCategory;
                }
            });
        }
    }
});
