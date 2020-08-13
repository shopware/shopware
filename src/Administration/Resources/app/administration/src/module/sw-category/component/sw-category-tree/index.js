import template from './sw-category-tree.html.twig';
import './sw-category-tree.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-category-tree', {
    template,

    inject: ['repositoryFactory', 'syncService'],

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null
        },

        currentLanguageId: {
            type: String,
            required: true
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        },

        allowCreate: {
            type: Boolean,
            required: false,
            default: true
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            loadedCategories: {},
            translationContext: 'sw-category',
            linkContext: 'sw.category.detail',
            isLoadingInitialData: true,
            loadedParentIds: []
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        category() {
            return Shopware.State.get('swCategoryDetail').category;
        },

        categories() {
            return Object.values(this.loadedCategories);
        },

        disableContextMenu() {
            if (!this.allowEdit) {
                return true;
            }

            return this.currentLanguageId !== Shopware.Context.api.systemLanguageId;
        },

        contextMenuTooltipText() {
            if (!this.allowEdit) {
                return this.$tc('sw-privileges.tooltip.warning');
            }

            return null;
        }
    },

    watch: {
        category(newVal, oldVal) {
            // load data when path is available
            if (!oldVal && this.isLoadingInitialData) {
                this.openInitialTree();
                return;
            }

            // back to index
            if (newVal === null) {
                return;
            }

            // reload after save
            if (oldVal && newVal.id === oldVal.id) {
                this.categoryRepository.get(newVal.id, Shopware.Context.api).then((newCategory) => {
                    this.loadedCategories[newCategory.id] = newCategory;
                    this.loadedCategories = { ...this.loadedCategories };
                });
            }
        },

        currentLanguageId() {
            this.openInitialTree();
        }
    },

    methods: {
        createdComponent() {
            if (!this.categoryId) {
                this.loadRootCategories().then(() => {
                    this.isLoadingInitialData = false;
                });
            }
        },

        openInitialTree() {
            this.isLoadingInitialData = true;
            this.loadedCategories = {};
            this.loadedParentIds = [];

            this.loadRootCategories()
                .then(() => {
                    if (!this.category || this.category.path === null) {
                        this.isLoadingInitialData = false;
                        return Promise.resolve();
                    }

                    const parentIds = this.category.path.split('|').filter((id) => !!id);
                    const parentPromises = [];

                    parentIds.forEach((id) => {
                        const searchCriteria = (new Criteria(1, 1))
                            .addAssociation('children');

                        searchCriteria.getAssociation('children')
                            .setLimit(500);

                        const promise = this.categoryRepository.get(id, Shopware.Context.api, searchCriteria)
                            .then((result) => {
                                this.addCategories([result, ...result.children]);
                            });
                        parentPromises.push(promise);
                    });

                    return Promise.all(parentPromises).then(() => {
                        this.isLoadingInitialData = false;
                    });
                });
        },

        onUpdatePositions({ draggedItem, oldParentId, newParentId }) {
            if (draggedItem.children.length > 0) {
                draggedItem.children.forEach((child) => {
                    this.removeFromStore(child.id);
                });
                this.loadedParentIds = this.loadedParentIds.filter((id) => id !== draggedItem.id);
            }

            this.syncSiblings({ parentId: newParentId }).then(() => {
                if (oldParentId !== newParentId) {
                    this.syncSiblings({ parentId: oldParentId });
                }
            });
        },

        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);
            this.categoryRepository.syncDeleted(ids, Shopware.Context.api).then(() => {
                ids.forEach(id => this.removeFromStore(id));
            });
        },

        onDeleteCategory({ data: category }) {
            if (category.isNew()) {
                delete this.loadedCategories[category.id];
                this.loadedCategories = { ...this.loadedCategories };
                return Promise.resolve();
            }

            return this.categoryRepository.delete(category.id, Shopware.Context.api).then(() => {
                this.removeFromStore(category.id);

                if (category.parentId !== null) {
                    this.categoryRepository.get(category.parentId, Shopware.Context.api).then((updatedParent) => {
                        this.addCategory(updatedParent);
                    });
                }

                if (category.id === this.categoryId) {
                    this.$router.push({ name: 'sw.category.index' });
                }
            });
        },

        changeCategory(category) {
            const route = { name: 'sw.category.detail', params: { id: category.id } };
            if (this.category && this.categoryRepository.hasChanges(this.category)) {
                this.$emit('unsaved-changes', route);
            } else {
                this.$router.push(route);
            }
        },

        onGetTreeItems(parentId) {
            if (this.loadedParentIds.includes(parentId)) {
                return Promise.resolve();
            }

            this.loadedParentIds.push(parentId);
            const categoryCriteria = new Criteria(1, 500);
            categoryCriteria.addFilter(Criteria.equals('parentId', parentId));

            return this.categoryRepository.search(categoryCriteria, Shopware.Context.api).then((children) => {
                this.addCategories(children);
            }).catch(() => {
                this.loadedParentIds = this.loadedParentIds.filter((id) => {
                    return id !== parentId;
                });
            });
        },

        getChildrenFromParent(parentId) {
            return this.onGetTreeItems(parentId);
        },

        loadRootCategories() {
            const criteria = new Criteria();
            criteria.limit = 500;
            criteria.addFilter(Criteria.equals('parentId', null));
            return this.categoryRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.addCategories(result);
            });
        },

        createNewElement(contextItem, parentId, name = '') {
            if (!parentId && contextItem) {
                parentId = contextItem.parentId;
            }
            const newCategory = this.createNewCategory(name, parentId);
            this.addCategory(newCategory);
            return newCategory;
        },

        createNewCategory(name, parentId) {
            const newCategory = this.categoryRepository.create(Shopware.Context.api);

            newCategory.name = name;
            newCategory.parentId = parentId;
            newCategory.childCount = 0;
            newCategory.active = false;
            newCategory.visible = true;

            newCategory.save = () => {
                return this.categoryRepository.save(newCategory, Shopware.Context.api).then(() => {
                    const criteria = new Criteria();
                    criteria.setIds([newCategory.id, parentId].filter((id) => id !== null));
                    this.categoryRepository.search(criteria, Shopware.Context.api).then((categories) => {
                        this.addCategories(categories);
                    });
                });
            };

            return newCategory;
        },

        syncSiblings({ parentId }) {
            const siblings = this.categories.filter((category) => {
                return category.parentId === parentId;
            });

            return this.categoryRepository.sync(siblings, Shopware.Context.api).then(() => {
                this.loadedParentIds = this.loadedParentIds.filter(id => id !== parentId);
                return this.getChildrenFromParent(parentId);
            }).then(() => {
                this.categoryRepository.get(parentId, Shopware.Context.api).then((parent) => {
                    this.addCategory(parent);
                });
            });
        },

        addCategory(category) {
            this.loadedCategories = { ...this.loadedCategories, [category.id]: category };
        },

        addCategories(categories) {
            categories.forEach((category) => {
                this.loadedCategories[category.id] = category;
            });
            this.loadedCategories = { ...this.loadedCategories };
        },

        removeFromStore(id) {
            const deletedIds = this.getDeletedIds(id);
            this.loadedParentIds = this.loadedParentIds.filter((loadedId) => {
                return !deletedIds.includes(loadedId);
            });

            deletedIds.forEach((deleted) => {
                delete this.loadedCategories[deleted];
            });
            this.loadedCategories = { ...this.loadedCategories };
        },

        getDeletedIds(idToDelete) {
            const idsToDelete = [idToDelete];
            Object.keys(this.loadedCategories).forEach((id) => {
                const currentCategory = this.loadedCategories[id];
                if (currentCategory.parentId === idToDelete) {
                    idsToDelete.push(...this.getDeletedIds(id));
                }
            });
            return idsToDelete;
        }
    }
});
