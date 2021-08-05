import template from './sw-cms-list.html.twig';
import './sw-cms-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-cms-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            pages: [],
            linkedLayouts: [],
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 9,
            term: null,
            currentPageType: null,
            showMediaModal: false,
            currentPage: null,
            showDeleteModal: false,
            defaultMediaFolderId: null,
            listMode: 'grid',
            assignablePageTypes: ['categories', 'products'],
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

        sortOptions() {
            return [
                { value: 'createdAt:DESC', name: this.$tc('sw-cms.sorting.labelSortByCreatedDsc') },
                { value: 'createdAt:ASC', name: this.$tc('sw-cms.sorting.labelSortByCreatedAsc') },
                { value: 'updatedAt:DESC', name: this.$tc('sw-cms.sorting.labelSortByUpdatedDsc') },
                { value: 'updatedAt:ASC', name: this.$tc('sw-cms.sorting.labelSortByUpdatedAsc') },
            ];
        },

        sortPageTypes() {
            const sortPageTypes = [
                { value: '', name: this.$tc('sw-cms.sorting.labelSortByAllPages'), active: true },
                { value: 'page', name: this.$tc('sw-cms.sorting.labelSortByShopPages') },
                { value: 'landingpage', name: this.$tc('sw-cms.sorting.labelSortByLandingPages') },
                { value: 'product_list', name: this.$tc('sw-cms.sorting.labelSortByCategoryPages') },
                { value: 'product_detail', name: this.$tc('sw-cms.sorting.labelSortByProductPages') },
            ];

            return sortPageTypes;
        },

        pageTypes() {
            const pageTypes = {
                page: this.$tc('sw-cms.sorting.labelSortByShopPages'),
                landingpage: this.$tc('sw-cms.sorting.labelSortByLandingPages'),
                product_list: this.$tc('sw-cms.sorting.labelSortByCategoryPages'),
                product_detail: this.$tc('sw-cms.sorting.labelSortByProductPages'),
            };

            return pageTypes;
        },

        sortingConCat() {
            return `${this.sortBy}:${this.sortDirection}`;
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('previewMedia')
                .addAssociation('products')
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            if (this.currentPageType !== null) {
                criteria.addFilter(Criteria.equals('cms_page.type', this.currentPageType));
            }

            this.addLinkedLayoutsAggregation(criteria);

            return criteria;
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('adminMenu/collapseSidebar');

            this.setPageContext();
        },

        setPageContext() {
            this.getDefaultFolderId().then((folderId) => {
                this.defaultMediaFolderId = folderId;
            });
        },

        getList() {
            this.isLoading = true;

            return this.pageRepository.search(this.listCriteria).then((searchResult) => {
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

        /**
         * Determines whether a given CMS layout ("page") is in use already.
         */
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
            this.limit = (this.listMode === 'grid') ? 9 : 10;

            this.resetList();
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

        onDeleteCmsPage(page) {
            this.currentPage = page;
            this.showDeleteModal = true;
        },

        onDuplicateCmsPage(page) {
            this.isLoading = true;
            this.pageRepository.clone(page.id).then(() => {
                this.resetList();
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
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

        saveCmsPage(page) {
            this.isLoading = true;
            return this.pageRepository.save(page).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        deleteCmsPage(page) {
            const titleDeleteError = this.$tc('global.default.error');
            const messageDeleteError = this.$tc('sw-cms.components.cmsListItem.notificationDeleteErrorMessage');

            this.isLoading = true;
            return this.pageRepository.delete(page.id).then(() => {
                this.resetList();
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    title: titleDeleteError,
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
            }, {
                property: 'type',
                label: this.$tc('sw-cms.list.gridHeaderType'),
            }, {
                property: 'assignments',
                label: this.$tc('sw-cms.list.gridHeaderAssignments'),
                sortable: false,
            }, {
                property: 'createdAt',
                label: this.$tc('sw-cms.list.gridHeaderCreated'),
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
    },
});
