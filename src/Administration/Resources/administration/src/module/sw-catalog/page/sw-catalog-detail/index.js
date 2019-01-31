import { Component, State, Mixin } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-catalog-detail.html.twig';
import './sw-catalog-detail.scss';

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
            isLoading: false,
            item: null,
            currentEditMode: null,
            parent: null
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

        getCategoryById(id) {
            return this.categoryStore.getById(id);
        },

        getParentItem(parentId) {
            const parentCategory = this.getCategoryById(parentId);

            if (!parentCategory) {
                return null;
            }

            return {
                data: parentCategory,
                id: parentCategory.id,
                parentId: null,
                position: parentCategory.position,
                childCount: parentCategory.childCount
            };
        },

        getCategories(parentId = null, searchTerm = null) {
            const criteria = [];
            const params = {
                page: 1,
                limit: 500
            };

            criteria.push(CriteriaFactory.equals('catalogId', this.catalogId));

            if (parentId !== false) {
                criteria.push(CriteriaFactory.equals('parentId', parentId));
            }

            params.criteria = CriteriaFactory.multi('AND', ...criteria);

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
                    }
                },
                criteria: CriteriaFactory.equals('id', this.catalogId)
            };

            return this.catalogService.getList(aggregateParams).then((response) => {
                this.aggregations = response.aggregations;
            });
        },

        addFirstCategory(categoryName) {
            this.addCategoryName = categoryName;

            if (!this.addCategoryName.length || this.addCategoryName.length <= 0) {
                return;
            }

            const newCategory = this.createNewCategory(this.addCategoryName, null, 0);

            this.categories.forEach((category) => {
                if (category.parentId === null) {
                    category.position += 1;
                }
            });

            this.categories.push(newCategory);
            this.addCategoryName = '';

            const item = {
                data: newCategory,
                id: newCategory.id,
                parentId: null,
                position: newCategory.position,
                childCount: 0
            };

            this.addCategoryAfter(item);
        },

        onAddChildCategory(item) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            if (this.item === null) {
                this.item = item;
            }

            this.currentEditMode = this.onAddChildCategory;

            this.getCategories(item.data.id).then(() => {
                const parentCategory = item.data;
                this.parentItem = item;

                const newCategory = this.createNewCategory('', parentCategory.id, parentCategory.childCount);

                parentCategory.childCount = parseInt(parentCategory.childCount, 10) + 1;
                this.categories.push(newCategory);
                this.onEditCategory(newCategory);
            });
        },

        addCategoryBefore(item) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            this.currentEditMode = this.addCategoryBefore;
            const newCategory = this.createNewCategory('', item.parentId, item.position);

            this.categories.forEach((category) => {
                if (category.parentId === item.parentId) {
                    if (category.position >= item.position) {
                        category.position += 1;
                    }
                }
            });

            if (item.parentId !== null) {
                this.parentItem = this.getParentItem(item.parentId);
                this.parentItem.data.childCount = parseInt(this.parentItem.data.childCount, 10) + 1;
            }

            if (this.item === null) {
                this.item = item;
            }
            item.position += 1;

            this.categories.push(newCategory);
            this.onEditCategory(newCategory);
        },

        addCategoryAfter(item) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            this.currentEditMode = this.addCategoryAfter;

            const newCategory = this.createNewCategory('', item.parentId, item.position + 1);
            this.categories.forEach((category) => {
                if (category.parentId === item.parentId) {
                    if (category.position > item.position) {
                        category.position += 1;
                    }
                }
            });

            if (item.parentId !== null) {
                this.parentItem = this.getParentItem(item.parentId);
                this.parentItem.data.childCount = parseInt(this.parentItem.data.childCount, 10) + 1;
            }

            if (this.item === null) {
                this.item = item;
            }
            item.position += 1;

            this.categories.push(newCategory);
            this.onEditCategory(newCategory);
        },

        createNewCategory(name, parentId, position, childCount = 0) {
            const newCategory = this.categoryStore.create();

            newCategory.name = name;
            newCategory.catalogId = this.catalogId;
            newCategory.parentId = parentId;
            newCategory.position = position;
            newCategory.childCount = childCount;

            return newCategory;
        },

        onEditCategory(item) {
            this.currentEditCategory = item.id;

            this.$nextTick(() => {
                this._eventFromEdit = null;
                const categoryNameField = this.$el.querySelector('.sw-catalog-detail__edit-category-field input');
                categoryNameField.focus();
            });
        },

        onEditCategoryFinish(draft, event) {
            this._eventFromEdit = event;
            this.currentEditCategory = null;
            if (this.currentEditMode !== null) {
                this.currentEditMode(this.item);
            }
        },

        onDeleteCategory(item) {
            item.data.delete();

            if (this.parentItem && this.parentItem.data.childCount > 0) {
                this.parentItem.data.childCount = this.parentItem.data.childCount - 1;
            }
            this.parentItem = null;
            this.$emit('itemDeleted', item);
        },

        abortCategoryEdit(item) {
            if (this._eventFromEdit) {
                return;
            }
            this.currentEditCategory = null;
            if (this.currentEditMode !== null) {
                this.onDeleteCategory(item);
            }
            this.item = null;
            this.currentEditMode = null;
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
