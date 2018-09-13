import { Component, State, Mixin } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-catalog-detail.html.twig';
import './sw-catalog-detail.less';

Component.register('sw-catalog-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: ['catalogService'],

    data() {
        return {
            catalogId: null,
            catalog: {},
            categories: [],
            aggregations: {},
            addCategoryName: '',
            currentEditCategory: null,
            isLoading: false
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        categoryStore() {
            return State.getStore('category');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.catalogId = this.$route.params.id;
                this.catalog = this.catalogStore.getById(this.catalogId);

                this.getAggregations();
                this.getCategories();
            }
        },

        getCategories(parentId = null, searchTerm = null) {
            const criteria = [];
            const params = {
                page: 1,
                limit: 500
            };

            criteria.push(CriteriaFactory.term('catalogId', this.catalogId));

            if (parentId !== false) {
                criteria.push(CriteriaFactory.term('parentId', parentId));
            }

            params.criteria = CriteriaFactory.nested('AND', ...criteria);

            if (searchTerm !== null) {
                params.term = searchTerm;
            }

            this.isLoading = searchTerm !== null || parentId === null;

            return this.categoryStore.getList(params).then((response) => {
                response.items.forEach((category) => {
                    if (typeof this.categories.find(c => c.id === category.id) !== 'undefined') {
                        return;
                    }

                    this.categories.push(category);
                });

                this.isLoading = false;
                return response.items;
            });
        },

        getAggregations() {
            const aggregateParams = {
                page: 1,
                limit: 1,
                aggregations: {
                    productCount: {
                        count: { field: 'catalog.products.id' }
                    },
                    categoryCount: {
                        count: { field: 'catalog.categories.id' }
                    },
                    mediaCount: {
                        count: { field: 'catalog.media.id' }
                    }
                },
                criteria: CriteriaFactory.term('id', this.catalogId)
            };

            return this.catalogService.getList(aggregateParams).then((response) => {
                this.aggregations = response.aggregations;
            });
        },

        onAddCategory() {
            if (!this.addCategoryName.length || this.addCategoryName.length <= 0) {
                return;
            }

            const newCategory = this.categoryStore.create();

            newCategory.name = this.addCategoryName;
            newCategory.catalogId = this.catalogId;
            newCategory.parentId = null;
            newCategory.position = 0;

            this.categories.forEach((category) => {
                if (category.parentId === null) {
                    category.position += 1;
                }
            });

            this.categories.push(newCategory);
            this.addCategoryName = '';
        },

        onAddChildCategory(item) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            this.getCategories(item.data.id).then(() => {
                const parentCategory = item.data;
                const newCategory = this.categoryStore.create();

                newCategory.name = '';
                newCategory.catalogId = this.catalogId;
                newCategory.parentId = parentCategory.id;
                newCategory.position = 0;
                newCategory.childCount = 0;

                this.categories.forEach((category) => {
                    if (category.parentId === parentCategory.id) {
                        category.position += 1;
                    }
                });

                parentCategory.childCount = parseInt(parentCategory.childCount, 10) + 1;

                this.categories.push(newCategory);
                this.onEditCategory(newCategory);
            });
        },

        onEditCategory(item) {
            this.currentEditCategory = item.id;

            this.$nextTick(() => {
                const categoryNameField = this.$el.querySelector('.sw-catalog-detail__edit-category-field input');
                categoryNameField.focus();
            });
        },

        onEditCategoryFinish() {
            this.currentEditCategory = null;
        },

        onDeleteCategory(item) {
            item.data.delete();
        },

        searchCategories(searchTerm) {
            let parentId = false;

            if (searchTerm === null || !searchTerm.length) {
                parentId = null;
            }

            this.categories = [];
            return this.getCategories(parentId, searchTerm);
        },

        onSave() {
            this.isLoading = true;
            const associatedCategoryStore = this.catalog.getAssociation('categories');
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: this.catalog.name }
            );
            const titleSaveSuccess = this.$tc('global.notification.notificationSaveSuccessTitle');
            const messageSaveSuccess = this.$tc(
                'global.notification.notificationSaveSuccessMessage', 0, { entityName: this.catalog.name }
            );

            Object.keys(this.categoryStore.store).forEach((id) => {
                const category = this.categoryStore.store[id];

                if (!category.catalogId === this.catalogId) {
                    return;
                }

                associatedCategoryStore.add(category);
            });

            return this.catalog.save().then((response) => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
                this.isLoading = false;

                return response;
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                this.isLoading = false;
            });
        }
    }
});
