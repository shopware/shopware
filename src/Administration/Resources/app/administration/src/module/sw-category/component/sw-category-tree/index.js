import template from './sw-category-tree.html.twig';
import './sw-category-tree.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-category-tree', {
    template,

    inject: ['repositoryFactory', 'syncService'],

    mixins: ['notification'],

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },

        currentLanguageId: {
            type: String,
            required: true,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowCreate: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            loadedCategories: {},
            translationContext: 'sw-category',
            linkContext: 'sw.category.detail',
            isLoadingInitialData: true,
            loadedParentIds: [],
        };
    },

    computed: {
        ...mapState('swCategoryDetail', [
            'categoriesToDelete',
        ]),

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
        },

        criteria() {
            return new Criteria(1, 500)
                .addAssociation('navigationSalesChannels')
                .addAssociation('footerSalesChannels')
                .addAssociation('serviceSalesChannels');
        },

        criteriaWithChildren() {
            const parentCriteria = Criteria.fromCriteria(this.criteria).setLimit(1);
            parentCriteria.associations.push({
                association: 'children',
                criteria: Criteria.fromCriteria(this.criteria),
            });

            return parentCriteria;
        },

        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        defaultLayout() {
            return Shopware.State.get('swCategoryDetail').defaultLayout;
        },

        defaultLayoutCriteria() {
            const criteria = new Criteria(1, 1);
            criteria
                .addSorting(Criteria.sort('createdAt', 'ASC'))
                .addFilter(Criteria.multi(
                    'AND',
                    [
                        Criteria.equals('type', 'product_list'),
                        Criteria.equals('locked', true),
                    ],
                ));

            return criteria;
        },
    },

    watch: {
        categoriesToDelete(value) {
            if (value === undefined) {
                return;
            }

            this.$refs.categoryTree.onDeleteElements(value);

            Shopware.State.commit('swCategoryDetail/setCategoriesToDelete', {
                categoriesToDelete: undefined,
            });
        },

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
                const affectedCategoryIds = [
                    newVal.id,
                    ...oldVal.navigationSalesChannels.map(salesChannel => salesChannel.navigationCategoryId),
                    ...oldVal.footerSalesChannels.map(salesChannel => salesChannel.footerCategoryId),
                    ...oldVal.serviceSalesChannels.map(salesChannel => salesChannel.serviceCategoryId),
                ];

                const criteria = Criteria.fromCriteria(this.criteria)
                    .setIds(affectedCategoryIds.filter((value, index, self) => {
                        return value !== null && self.indexOf(value) === index;
                    }));

                this.categoryRepository.search(criteria).then((categories) => {
                    this.addCategories(categories);
                });
            }
        },

        currentLanguageId() {
            this.openInitialTree();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadDefaultLayout();

            if (this.category !== null) {
                this.openInitialTree();
            }

            if (!this.categoryId) {
                this.loadRootCategories().finally(() => {
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
                        const promise = this.categoryRepository.get(id, Shopware.Context.api, this.criteriaWithChildren)
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
                    this.syncSiblings({ parentId: oldParentId }).then(() => {
                        this.syncProducts(draggedItem.id);
                    });
                }
            });
        },

        syncProducts(categoryId) {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.multi('or', [
                Criteria.equals('categoriesRo.id', categoryId),
                Criteria.equals('categories.id', categoryId),
            ]));

            return this.productRepository.iterateIds(criteria, this.indexProducts);
        },

        indexProducts(ids) {
            const headers = this.productRepository.buildHeaders();

            const initContainer = Shopware.Application.getContainer('init');
            const httpClient = initContainer.httpClient;

            return httpClient.post('/_action/index-products', { ids }, { headers });
        },

        checkedElementsCount(count) {
            this.$emit('category-checked-elements-count', count);
        },

        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);

            const hasNavigationCategories = ids.some((id) => {
                return this.loadedCategories[id].navigationSalesChannels !== null
                    && this.loadedCategories[id].navigationSalesChannels.length > 0;
            });

            if (hasNavigationCategories) {
                this.createNotificationError({
                    message: this.$tc('sw-category.general.errorNavigationEntryPointMultiple'),
                });

                const categories = ids.map((id) => {
                    return this.loadedCategories[id];
                });

                // reload to remove selection
                ids.forEach((deleted) => {
                    this.$delete(this.loadedCategories, deleted);
                });
                this.$nextTick(() => {
                    this.addCategories(categories);
                });

                return;
            }

            this.categoryRepository.syncDeleted(ids, Shopware.Context.api).then(() => {
                ids.forEach(id => this.removeFromStore(id));
            });
        },

        onDeleteCategory({ data: category, children }) {
            if (category.isNew()) {
                this.$delete(this.loadedCategories, category.id);
                return Promise.resolve();
            }

            if (this.isErrorNavigationEntryPoint(category)) {
                // remove delete flags
                category.isDeleted = false;
                if (children.length > 0) {
                    children.forEach((child) => {
                        child.data.isDeleted = false;
                    });
                }

                // reinsert category in sorting because the tree
                // already overwrites the afterCategoryId of the following category
                const next = Object.values(this.loadedCategories).find((item) => {
                    return item.parentId === category.parentId && item.afterCategoryId === category.afterCategoryId;
                });
                if (next !== null) {
                    next.afterCategoryId = category.id;
                }

                // reload after changes
                this.loadedCategories = { ...this.loadedCategories };

                this.createNotificationError({ message: this.entryPointWarningMessage(category) });
                return Promise.resolve();
            }

            return this.categoryRepository.delete(category.id).then(() => {
                this.removeFromStore(category.id);

                if (category.parentId !== null) {
                    this.categoryRepository.get(
                        category.parentId,
                        Shopware.Context.api,
                        this.criteria,
                    ).then((updatedParent) => {
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
            const criteria = Criteria.fromCriteria(this.criteria);
            criteria.addFilter(Criteria.equals('parentId', parentId));
            // in case the criteria has been altered to search specific ids e.g. by dragndrop position change
            // reset all ids so categories can be found solely by parentId
            criteria.setIds([]);

            return this.categoryRepository.search(criteria).then((children) => {
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
            const criteria = Criteria.fromCriteria(this.criteria)
                .addFilter(Criteria.equals('parentId', null));

            return this.categoryRepository.search(criteria).then((result) => {
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
            const newCategory = this.categoryRepository.create();

            newCategory.name = name;
            newCategory.parentId = parentId;
            newCategory.childCount = 0;
            newCategory.active = false;
            newCategory.visible = true;
            newCategory.cmsPageId = this.defaultLayout;

            newCategory.save = () => {
                return this.categoryRepository.save(newCategory).then(() => {
                    const criteria = Criteria.fromCriteria(this.criteria)
                        .setIds([newCategory.id, parentId].filter((id) => id !== null));
                    this.categoryRepository.search(criteria).then((categories) => {
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

            return this.categoryRepository.sync(siblings).then(() => {
                this.loadedParentIds = this.loadedParentIds.filter(id => id !== parentId);
                return this.getChildrenFromParent(parentId);
            }).then(() => {
                this.categoryRepository.get(parentId, Shopware.Context.api, this.criteria).then((parent) => {
                    this.addCategory(parent);
                });
            });
        },

        addCategory(category) {
            if (!category) {
                return;
            }

            this.$set(this.loadedCategories, category.id, category);
        },

        addCategories(categories) {
            categories.forEach((category) => {
                this.$set(this.loadedCategories, category.id, category);
            });
        },

        removeFromStore(id) {
            const deletedIds = this.getDeletedIds(id);
            this.loadedParentIds = this.loadedParentIds.filter((loadedId) => {
                return !deletedIds.includes(loadedId);
            });

            deletedIds.forEach((deleted) => {
                this.$delete(this.loadedCategories, deleted);
            });
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
        },

        getCategoryUrl(category) {
            return this.$router.resolve({
                name: this.linkContext,
                params: { id: category.id },
            }).href;
        },

        isHighlighted({ data: category }) {
            return (category.navigationSalesChannels !== null && category.navigationSalesChannels.length > 0)
                || (category.serviceSalesChannels !== null && category.serviceSalesChannels.length > 0)
                || (category.footerSalesChannels !== null && category.footerSalesChannels.length > 0);
        },

        loadDefaultLayout() {
            return this.cmsPageRepository.search(this.defaultLayoutCriteria).then((response) => {
                Shopware.State.commit('swCategoryDetail/setDefaultLayout', response[0]);
            });
        },

        isErrorNavigationEntryPoint(category) {
            const { navigationSalesChannels, serviceSalesChannels, footerSalesChannels } = category;

            return [
                navigationSalesChannels,
                serviceSalesChannels,
                footerSalesChannels,
            ].some(navigation => navigation !== null && navigation?.length > 0);
        },

        entryPointWarningMessage(category) {
            const { serviceSalesChannels, footerSalesChannels } = category;

            if (serviceSalesChannels !== null && serviceSalesChannels?.length > 0) {
                return this.$tc(
                    'sw-category.general.errorNavigationEntryPoint',
                    0,
                    { entryPointLabel: this.$tc('sw-category.base.entry-point-card.types.labelServiceNavigation') },
                );
            }

            if (footerSalesChannels !== null && footerSalesChannels?.length > 0) {
                return this.$tc(
                    'sw-category.general.errorNavigationEntryPoint',
                    0,
                    { entryPointLabel: this.$tc('sw-category.base.entry-point-card.types.labelFooterNavigation') },
                );
            }

            return this.$tc(
                'sw-category.general.errorNavigationEntryPoint',
                0,
                { entryPointLabel: this.$tc('sw-category.base.entry-point-card.types.labelMainNavigation') },
            );
        },
    },
});
