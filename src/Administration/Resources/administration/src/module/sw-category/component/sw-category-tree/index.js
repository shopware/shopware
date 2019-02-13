import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-category-tree.html.twig';
import './sw-category-tree.scss';

Component.register('sw-category-tree', {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        categories: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            activeCategoryId: this.$route.params.id || null,
            currentEditCategory: null,
            item: null,
            checkedCategories: {},
            checkedCategoriesCount: 0,
            currentEditMode: null,
            parentItem: null,
            _eventFromEdit: null,
            addCategoryPosition: null,
            openedTreeById: false
        };
    },

    created() {
        if (this.activeCategoryId) {
            this.openTreeById();
        }
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        }
    },

    watch: {
        '$route.params.id'() {
            this.getActiveCategory();
        }
    },

    methods: {
        getActiveCategory() {
            this.activeCategoryId = this.$route.params.id;
        },

        onUpdatePositions() {
            this.saveCategories();
        },

        saveCategories() {
            this.$emit('sw-category-on-save');
        },

        refreshCategories() {
            this.$emit('sw-category-on-refresh');
        },

        onDeleteCategory(item) {
            const category = this.categoryStore.getById(item.id);
            category.delete(true).then(() => {
                this.refreshCategories();
                if (item.id === this.activeCategoryId) {
                    this.$emit('sw-category-on-reset-details');
                }
            });

            if (this.parentItem && this.parentItem.data.childCount > 0) {
                this.parentItem.data.childCount = this.parentItem.data.childCount - 1;
            }
            this.parentItem = null;
        },

        onDuplicateCategory(item) {
            this.$emit('sw-category-on-duplicate', item);
        },

        addFirstCategory(categoryName) {
            if (!categoryName.length || categoryName.length <= 0) {
                return;
            }

            const newCategory = this.createNewCategory(categoryName, null, 0);

            this.categories.forEach((category) => {
                if (category.parentId === null) {
                    category.position += 1;
                }
            });

            this.categories.push(newCategory);
            this.saveCategories();

            const item = {
                data: newCategory,
                id: newCategory.id,
                parentId: null,
                position: newCategory.position,
                childCount: 0
            };

            this.addCategoryAfter(item);
        },

        addSubcategory(item) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            if (this.item === null) {
                this.item = item;
            }

            this.currentEditMode = this.addSubcategory;

            this.getChildrenFromParent(item.id).then(() => {
                const parentCategory = item.data;
                this.parentItem = item;
                const newCategory = this.createNewCategory('', parentCategory.id, parentCategory.childCount);

                parentCategory.childCount = parseInt(parentCategory.childCount, 10) + 1;
                this.categories.push(newCategory);
                this.onEditCategory(newCategory);
            });
        },

        addCategory(item, pos) {
            if (!item || !item.data || !item.data.id || this.currentEditCategory !== null) {
                return;
            }

            if (this.addCategoryPosition === null) {
                this.addCategoryPosition = pos;
            }

            this.currentEditMode = this.addCategory;

            let itemPosition = item.position;
            if (this.addCategoryPosition === 'after') {
                itemPosition += 1;
            }
            const newCategory = this.createNewCategory('', item.parentId, itemPosition);

            this.categories.forEach((category) => {
                this.setCategoryPositionsWithNewCategory(category, item);
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

        setCategoryPositionsWithNewCategory(category, item) {
            if (category.parentId !== item.parentId) {
                return;
            }
            if (this.addCategoryPosition === 'before' && category.position >= item.position) {
                category.position += 1;
            }
            if (this.addCategoryPosition === 'after' && category.position > item.position) {
                category.position += 1;
            }
        },

        createNewCategory(name, parentId, position, childCount = 0) {
            const newCategory = this.categoryStore.create();

            newCategory.name = name;
            newCategory.parentId = parentId;
            newCategory.position = position;
            newCategory.childCount = childCount;

            return newCategory;
        },

        getItemById(itemId) {
            return this.categoryStore.getByIdAsync(itemId);
        },

        getParentItem(parentId) {
            this.getItemById(parentId).then((parentCategory) => {
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
            });
        },

        onEditCategory(item) {
            this.currentEditCategory = item.id;

            this.$nextTick(() => {
                this._eventFromEdit = null;
                const categoryNameField = this.$el.querySelector('.sw-category-detail__edit-category-field input');
                categoryNameField.focus();
            });
        },

        onEditCategoryFinish(draft, event) {
            this.saveCategories();
            this._eventFromEdit = event;
            this.currentEditCategory = null;
            if (this.currentEditMode !== null) {
                this.currentEditMode(this.item);
            }
        },

        abortCreateCategory(item) {
            if (this._eventFromEdit) {
                return;
            }
            this.currentEditCategory = null;
            if (this.currentEditMode !== null) {
                this.onDeleteCategory(item);
            }
            this.item = null;
            this.currentEditMode = null;
            this.addCategoryPosition = null;
        },

        batchDelete() {
            Object.values(this.checkedCategories).forEach((itemId) => {
                const category = this.categoryStore.getById(itemId);
                category.delete(true);
                if (itemId === this.activeCategoryId) {
                    this.$emit('sw-category-on-reset-details');
                }
                return true;
            });

            this.checkedCategories = {};
            this.checkedCategoriesCount = 0;
            this.saveCategories();
        },

        deleteSelectedCategories() {
            if (this.checkedCategories.length <= 0) {
                return;
            }
            this.batchDelete();
        },

        checkItem(item) {
            if (item.checked) {
                this.checkedCategories[item.id] = item.id;
                this.checkedCategoriesCount += 1;
            } else {
                delete this.checkedCategories[item.id];
                this.checkedCategoriesCount -= 1;
            }
        },

        getChildren(parentId) {
            this.$emit('sw-category-load-children', parentId);
        },

        getChildrenFromParent(parentId) {
            return this.$parent.$parent.getCategories(parentId);
        },

        openTreeById() {
            this.getParentIdsByItemId();
        },

        getParentIdsByItemId(id = this.activeCategoryId) {
            this.getItemById(id).then((category) => {
                if (!category.path) {
                    return;
                }
                const parentPath = category.path;
                let parentIds = parentPath.split('|').reverse();
                parentIds = parentIds.filter((parent) => {
                    return parent !== '';
                });
                this.getParentItems(parentIds);
            });
        },

        getParentItems(ids) {
            const promises = [];

            ids.forEach((id) => {
                promises.push(this.getChildrenFromParent(id));
            });

            Promise.all(promises).then(() => {
                this.openedTreeById = true;
            });
        }
    }
});
