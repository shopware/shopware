import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-cms-list.html.twig';
import './sw-cms-list.scss';

Component.register('sw-cms-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            pages: [],
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'dsc',
            term: '',
            disableRouteParams: true,
            noMorePages: false,
            criteria: null,
            showMediaModal: false,
            currentPage: null,
            showDeleteModal: false
        };
    },

    computed: {
        pageStore() {
            return State.getStore('cms_page');
        },

        languageStore() {
            return State.getStore('language');
        },

        defaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        sortOptions() {
            return [
                { value: 'createdAt:dsc', name: this.$tc('sw-cms.sorting.labelSortByCreatedDsc') },
                { value: 'createdAt:asc', name: this.$tc('sw-cms.sorting.labelSortByCreatedAsc') },
                { value: 'updatedAt:dsc', name: this.$tc('sw-cms.sorting.labelSortByUpdatedDsc') },
                { value: 'updatedAt:asc', name: this.$tc('sw-cms.sorting.labelSortByUpdatedAsc') }
            ];
        },

        sortPageTypes() {
            return [
                { value: '', name: this.$tc('sw-cms.sorting.labelSortByAllPages'), active: true },
                { value: 'page', name: this.$tc('sw-cms.sorting.labelSortByShopPages') },
                { value: 'landingpage', name: this.$tc('sw-cms.sorting.labelSortByLandingPages') },
                { value: 'product_list', name: this.$tc('sw-cms.sorting.labelSortByCategoryPages') },
                { value: 'product_detail', name: this.$tc('sw-cms.sorting.labelSortByProductPages'), disabled: true }
            ];
        },

        sortingConCat() {
            return `${this.sortBy}:${this.sortDirection}`;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // ToDo: Make the navigation state accessible via global state
            this.$root.$children[0].$children[2].$children[0].isExpanded = false;

            // ToDo: Remove, when language handling is added to CMS
            this.languageStore.setCurrentId(this.languageStore.systemLanguageId);

            this.setPageContext();
        },

        setPageContext() {
            this.getDefaultFolderId().then((folderId) => {
                this.pageStore.defaultMediaFolderId = folderId;
            });
        },

        getDefaultFolderId() {
            return this.defaultFolderStore.getList({
                limit: 1,
                criteria: CriteriaFactory.equals('entity', this.pageStore._entityName),
                associations: {
                    folder: {}
                }
            }).then(({ items }) => {
                if (items.length !== 1) {
                    return null;
                }

                const defaultFolder = items[0];
                if (defaultFolder.folder.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        handleScroll(event) {
            const scrollTop = event.srcElement.scrollTop;
            const scrollHeight = event.srcElement.scrollHeight;
            const offsetHeight = event.srcElement.offsetHeight;
            const bottomOfWindow = scrollTop === (scrollHeight - offsetHeight);

            if (bottomOfWindow) {
                this.getList(false);
            }
        },

        getList(filtered = true) {
            if (filtered) {
                this.page = 1;
                this.pages = [];
                this.noMorePages = false;
            }

            if (this.isLoading || this.noMorePages) {
                return false;
            }

            this.isLoading = true;
            const params = this.getListingParams();

            if (this.criteria) {
                params.criteria = this.criteria;
            }

            return this.pageStore.getList(params).then((response) => {
                if (response.items.length > 0) {
                    this.page += 1;
                } else {
                    this.noMorePages = true;
                }

                this.total = response.total;
                this.pages.push(...response.items);
                this.isLoading = false;

                return this.pages;
            });
        },

        onChangeLanguage() {
            this.getList(false);
        },

        onListItemClick(page) {
            this.$router.push({ name: 'sw.cms.detail', params: { id: page.id } });
        },

        onSortingChanged(value) {
            [this.sortBy, this.sortDirection] = value.split(':');
            this.getList();
        },

        onSearch(value) {
            this.term = value;
            this.getList();
        },

        onSortPageType(value) {
            if (!value) {
                this.criteria = null;
                this.getList();
                return;
            }

            this.criteria = CriteriaFactory.equals('cms_page.type', value);
            this.getList();
        },

        onCreateNewLayout() {
            this.$router.push({ name: 'sw.cms.create' });
        },

        onPreviewChange(page) {
            this.showMediaModal = true;
            this.currentPage = page;
        },

        onPreviewImageRemove(page) {
            page.previewMediaId = null;
            page.save();
            page.previewMedia = null;
        },

        onModalClose() {
            this.showMediaModal = false;
            this.currentPage = null;
        },

        onPreviewImageChange([image]) {
            this.currentPage.previewMediaId = image.id;
            this.currentPage.save();
            this.currentPage.previewMedia = image;
        },

        onDeleteCmsPage(page) {
            this.currentPage = page;
            this.showDeleteModal = true;
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

        deleteCmsPage(page) {
            const titleDeleteError = this.$tc('sw-cms.components.cmsListItem.notificationDeleteErrorTitle');
            const messageDeleteError = this.$tc('sw-cms.components.cmsListItem.notificationDeleteErrorMessage');

            this.currentPage.delete(true).then(() => {
                const deletedPageIdx = this.pages.findIndex(i => i.id === page.id);
                this.pages.splice(deletedPageIdx, 1);
            }).catch(() => {
                this.createNotificationError({
                    title: titleDeleteError,
                    message: messageDeleteError
                });
            });
        }
    }
});
