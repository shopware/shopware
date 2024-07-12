/**
 * @package buyers-experience
 */
import template from './sw-cms-list.html.twig';
import './sw-cms-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'systemConfigApiService',
        'cmsPageTypeService',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('user-settings'),
    ],

    data() {
        return {
            pages: [],
            linkedLayouts: [],
            isLoading: false,
            cardViewIdentifier: 'grid.cms.sw-cms-list-grid',
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 9,
            limitDefaults: {
                gridView: 10,
                cardView: 9,
            },
            associationLimit: 25,
            term: '',
            currentPageType: null,
            showMediaModal: false,
            currentPage: null,
            showRenameModal: false,
            newName: null,
            showDeleteModal: false,
            defaultMediaFolderId: null,
            listMode: 'grid',
            assignablePageTypes: ['categories', 'products'],
            searchConfigEntity: 'cms_page',
            showLayoutSetAsDefaultModal: false,
            defaultCategoryId: '',
            defaultProductId: '',
            newDefaultLayout: undefined,
            maxVisibleAssignedPages: 3,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        pageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        defaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        columnConfig() {
            return this.getColumnConfig();
        },

        sortPageTypes() {
            const sortByAllPagesOption = {
                value: '',
                name: this.$tc('sw-cms.sorting.labelSortByAllPages'),
                active: true,
            };

            return this.cmsPageTypeService.getTypes().reduce((accumulator, pageType) => {
                accumulator.push({
                    value: pageType.name,
                    name: this.$tc(pageType.title),
                });

                return accumulator;
            }, [sortByAllPagesOption]);
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.getAssociation('categories')
                .addSorting(Criteria.sort('name', 'ASC'))
                .setLimit(this.associationLimit);
            criteria.getAssociation('products')
                .addSorting(Criteria.sort('name', 'ASC'))
                .setLimit(this.associationLimit);
            criteria.addAssociation('previewMedia')
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            if (this.currentPageType !== null) {
                criteria.addFilter(Criteria.equals('cms_page.type', this.currentPageType));
            }

            this.addLinkedLayoutsAggregation(criteria);
            this.addPageAggregations(criteria);

            return criteria;
        },

        associatedCategoryBuckets() {
            return this.pages.aggregations?.categories?.buckets || [];
        },

        associatedProductBuckets() {
            return this.pages.aggregations?.products?.buckets || [];
        },

        /**
         * Returns a set of criteria/query objects which designate linked layouts.
         *
         * @internal
         */
        isLinkedCriteria() {
            return [
                {
                    type: 'multi',
                    operator: 'OR',
                    queries: this.assignablePageTypes.map(
                        name => Criteria.not('OR', [Criteria.equals(`${name}.id`, null)]),
                    ),
                },
            ];
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('adminMenu/collapseSidebar');

            if (this.acl.can('user_config:read')) {
                this.loadGridUserSettings();
            }

            if (this.acl.can('system_config:read')) {
                this.getDefaultLayouts();
            }

            this.setPageContext();
            this.resetList();
        },

        async loadGridUserSettings() {
            const settings = await this.getUserSettings(this.cardViewIdentifier);
            if (!settings) {
                return;
            }

            this.listMode = settings.listMode;
            this.sortBy = settings.sortBy;
            this.sortDirection = settings.sortDirection;

            this.updateLimit();
        },

        updateLimit() {
            this.limit = (this.listMode === 'grid') ? this.limitDefaults.cardView : this.limitDefaults.gridView;
        },

        saveGridUserSettings() {
            if (!this.acl.can('user_config:create') || !this.acl.can('user_config:update')) {
                console.warn('Did not persist user config, as permissions are missing.');

                return;
            }

            this.saveUserSettings(this.cardViewIdentifier, {
                listMode: this.listMode,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
            });
        },

        setPageContext() {
            this.getDefaultFolderId().then((folderId) => {
                this.defaultMediaFolderId = folderId;
            });
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.listCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            return this.pageRepository.search(criteria).then((searchResult) => {
                this.total = searchResult.total;
                this.pages = searchResult;

                if (searchResult.aggregations?.linkedLayouts) {
                    this.linkedLayouts = searchResult.aggregations.linkedLayouts.entities;
                }

                this.isLoading = false;

                return this.pages;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        /**
         * @internal
         */
        addLinkedLayoutsAggregation(criteria) {
            const linkedLayoutsFilter = Criteria.filter('linkedLayoutsFilter', this.isLinkedCriteria, {
                name: 'linkedLayouts',
                type: 'entity',
                definition: 'cms_page',
                field: 'id',
            });

            criteria.addAggregation(linkedLayoutsFilter);
        },

        addPageAggregations(criteria) {
            return criteria.addAggregation(Criteria.terms(
                'products',
                'id',
                null,
                null,
                Criteria.count('productCount', 'products.id'),
            )).addAggregation(Criteria.terms(
                'categories',
                'id',
                null,
                null,
                Criteria.count('categoryCount', 'categories.id'),
            ));
        },

        showDefaultLayoutContextMenu(cmsPage) {
            if (!this.acl.can('system_config:read')) {
                return false;
            }

            if (cmsPage.type === 'product_list') {
                return this.defaultCategoryId !== cmsPage.id;
            }

            if (cmsPage.type === 'product_detail') {
                return this.defaultProductId !== cmsPage.id;
            }

            return false;
        },

        async getDefaultLayouts() {
            const response = await this.systemConfigApiService.getValues('core.cms');

            this.defaultCategoryId = response['core.cms.default_category_cms_page'];
            this.defaultProductId = response['core.cms.default_product_cms_page'];
        },

        onOpenLayoutSetAsDefault(page) {
            this.newDefaultLayout = { id: page.id, type: page.type };
            this.showLayoutSetAsDefaultModal = true;
        },

        onCloseLayoutSetAsDefault() {
            this.newDefaultLayout = undefined;
            this.showLayoutSetAsDefaultModal = false;
        },

        async onConfirmLayoutSetAsDefault() {
            let configKey = 'category_cms_page';

            const { id, type } = this.newDefaultLayout;
            if (type === 'product_detail') {
                this.defaultProductId = id;
                configKey = 'product_cms_page';
            } else {
                this.defaultCategoryId = id;
            }

            await this.systemConfigApiService.saveValues({
                [`core.cms.default_${configKey}`]: id,
            });

            this.showLayoutSetAsDefaultModal = false;
        },

        layoutIsLinked(pageId) {
            return this.linkedLayouts.some(page => page.id === pageId);
        },

        resetList() {
            this.page = 1;
            this.pages = [];
            this.updateRoute({
                page: this.page,
                limit: this.limit,
                term: this.term,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
            });
            this.getList();
        },

        getDefaultFolderId() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'cms_page'));

            return this.defaultFolderRepository.search(criteria).then((searchResult) => {
                const defaultFolder = searchResult.first();
                if (defaultFolder.folder?.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.resetList();
        },

        onListItemClick(page) {
            this.$router.push({ name: 'sw.cms.detail', params: { id: page.id } });
        },

        onSortingChanged(value) {
            [this.sortBy, this.sortDirection] = value.split(':');
            this.resetList();
            this.saveGridUserSettings();
        },

        onSearch(value = null) {
            if (!value.length || value.length <= 0) {
                this.term = null;
            } else {
                this.term = value;
            }

            this.resetList();
        },

        onSortPageType(value = null) {
            if (!value.length || value.length <= 0) {
                this.currentPageType = null;
            } else {
                this.currentPageType = value;
            }

            this.resetList();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;

            this.getList();
            this.updateRoute({
                page: this.page,
                limit: this.limit,
            });
        },

        onCreateNewLayout() {
            this.$router.push({ name: 'sw.cms.create' });
        },

        onListModeChange() {
            this.listMode = (this.listMode === 'grid') ? 'list' : 'grid';

            this.updateLimit();
            this.resetList();
            this.saveGridUserSettings();
        },

        onPreviewChange(page) {
            this.showMediaModal = true;
            this.currentPage = page;
        },

        onPreviewImageRemove(page) {
            page.previewMediaId = null;
            page.previewMedia = null;
            this.saveCmsPage(page);
        },

        onModalClose() {
            this.showMediaModal = false;
            this.currentPage = null;
        },

        onPreviewImageChange([image]) {
            this.currentPage.previewMediaId = image.id;
            this.saveCmsPage(this.currentPage);
            this.currentPage.previewMedia = image;
        },

        onRenameCmsPage(page) {
            this.currentPage = page;
            this.showRenameModal = true;
        },

        onCloseRenameModal() {
            this.currentPage = null;
            this.showRenameModal = false;
        },

        onConfirmPageRename() {
            if (this.newName) {
                this.currentPage.name = this.newName;
                this.saveCmsPage(this.currentPage);
                this.getList();
            }
            this.newName = null;
            this.currentPage = null;
            this.showRenameModal = false;
        },

        onDeleteCmsPage(page) {
            this.currentPage = page;
            this.showDeleteModal = true;
        },

        onDuplicateCmsPage(page, behavior = { overwrites: {} }) {
            if (!behavior.overwrites) {
                behavior.overwrites = {};
            }

            if (!behavior.overwrites.name) {
                behavior.overwrites.name = `${page.name} - ${this.$tc('global.default.copy')}`;
            }

            this.isLoading = true;
            this.pageRepository.clone(page.id, behavior, Shopware.Context.api).then(() => {
                this.resetList();
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },

        onCloseDeleteModal() {
            this.currentPage = null;
            this.showDeleteModal = false;
        },

        onConfirmPageDelete() {
            this.deleteCmsPage(this.currentPage);

            this.currentPage = null;
            this.showDeleteModal = false;
        },

        saveCmsPage(page, context = Shopware.Context.api) {
            this.isLoading = true;
            return this.pageRepository.save(page, context).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        deleteCmsPage(page) {
            const messageDeleteError = this.$tc('sw-cms.components.cmsListItem.notificationDeleteErrorMessage');

            this.isLoading = true;
            return this.pageRepository.delete(page.id).then(() => {
                this.resetList();
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    message: messageDeleteError,
                });
            });
        },

        getColumnConfig() {
            return [{
                property: 'name',
                label: this.$tc('sw-cms.list.gridHeaderName'),
                inlineEdit: 'string',
                primary: true,
                sortable: false,
            }, {
                property: 'type',
                label: this.$tc('sw-cms.list.gridHeaderType'),
                sortable: false,
            }, {
                property: 'assignments',
                label: this.$tc('sw-cms.list.gridHeaderAssignments'),
                sortable: false,
            }, {
                property: 'assignedPages',
                label: this.$tc('sw-cms.list.gridHeaderAssignedPages'),
                sortable: false,
                visible: false,
            }, {
                property: 'createdAt',
                label: this.$tc('sw-cms.list.gridHeaderCreated'),
                sortable: false,
            }, {
                property: 'updatedAt',
                label: this.$tc('sw-cms.list.gridHeaderUpdated'),
                sortable: false,
                visible: false,
            }];
        },

        deleteDisabledToolTip(page) {
            if (page.type === 'product_detail') {
                return {
                    showDelay: 300,
                    message: this.$tc('sw-cms.general.deleteDisabledProductToolTip'),
                    disabled: !this.layoutIsLinked(page.id),
                };
            }

            return {
                showDelay: 300,
                message: this.$tc('sw-cms.general.deleteDisabledToolTip'),
                disabled: !this.layoutIsLinked(page.id),
            };
        },

        getPageType(page) {
            const isDefault = [this.defaultProductId, this.defaultCategoryId].includes(page.id);
            const defaultText = this.$tc('sw-cms.components.cmsListItem.defaultLayout');
            const typeLabel = this.$tc(this.cmsPageTypeService.getType(page.type)?.title);

            return isDefault ? `${defaultText} - ${typeLabel}` : typeLabel;
        },

        getPageCategoryCount(page) {
            return Object.values(this.associatedCategoryBuckets).find((bucket) => {
                return bucket.key === page.id;
            })?.categoryCount?.count || 0;
        },

        getPageProductCount(page) {
            return Object.values(this.associatedProductBuckets).find((bucket) => {
                return bucket.key === page.id;
            })?.productCount?.count || 0;
        },

        getPageCount(page) {
            const pageCount = this.getPageCategoryCount(page) + this.getPageProductCount(page);
            return pageCount > 0 ? pageCount : '-';
        },

        getPages(page) {
            return [
                ...page.categories.map((item) => item.name),
                ...page.products.map((item) => item.name),
            ];
        },

        getPagesString(page) {
            const items = this.getPages(page);
            let pagesString = [...items].splice(0, this.maxVisibleAssignedPages).join(', ');

            if (this.maxVisibleAssignedPages < items.length) {
                pagesString += ', ...';
            }

            return pagesString;
        },

        getPagesTooltip(page) {
            const items = this.getPages(page);
            let message = items.join(', ');

            if (this.associationLimit < this.getPageCount(page)) {
                message += ', ...';
            }

            return {
                width: 300,
                message,
                disabled: this.maxVisibleAssignedPages >= items.length,
            };
        },

        optionContextDeleteDisabled(page) {
            return this.getPageCategoryCount(page) > 0 ||
                this.getPageProductCount(page) > 0 ||
                !this.acl.can('cms.deleter');
        },
    },
};
