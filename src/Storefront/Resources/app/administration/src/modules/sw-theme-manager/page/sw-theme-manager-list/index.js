import template from './sw-theme-manager-list.html.twig';
import './sw-theme-manager-list.scss';

/**
 * @package sales-channel
 */

const { Component, Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-theme-manager-list', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('theme')
    ],

    data() {
        return {
            themes: [],
            isLoading: false,
            total: 0,
            disableRouteParams: true,
            salesChannelId: this.$route.params.id,
            listMode: 'grid',
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 9,
            term: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        columnConfig() {
            return this.getColumnConfig();
        },

        sortOptions() {
            return [
                { value: 'createdAt:DESC', name: this.$tc('sw-theme-manager.sorting.labelSortByCreatedDsc') },
                { value: 'createdAt:ASC', name: this.$tc('sw-theme-manager.sorting.labelSortByCreatedAsc') },
                { value: 'updatedAt:DESC', name: this.$tc('sw-theme-manager.sorting.labelSortByUpdatedDsc') },
                { value: 'updatedAt:ASC', name: this.$tc('sw-theme-manager.sorting.labelSortByUpdatedAsc') }
            ];
        },

        sortingConCat() {
            return `${this.sortBy}:${this.sortDirection}`;
        },

        lockToolTip() {
            return {
                showDelay: 100,
                message: this.$tc('sw-theme-manager.general.lockedToolTip')
            };
        }
    },

    methods: {
        onRefresh() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('salesChannels');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addFilter(Criteria.equals('active', true));

            if (this.term !== null) {
                criteria.setTerm(this.term);
            }

            return this.themeRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                this.total = searchResult.total;
                this.themes = searchResult;
                this.isLoading = false;

                return this.pages;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        resetList() {
            this.page = 1;
            this.themes = [];
            this.updateRoute({
                page: this.page,
                limit: this.limit,
                term: this.term,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection
            });

            this.getList();
        },

        onChangeLanguage(languageId) {
            Shopware.Context.api.languageId = languageId;
            this.resetList();
        },

        onListItemClick(theme) {
            this.$router.push({ name: 'sw.theme.manager.detail', params: { id: theme.id } });
        },

        onSortingChanged(value) {
            [this.sortBy, this.sortDirection] = value.split(':');
            this.resetList();
        },

        onSearch(value = null) {
            this.term = value.length > 0 ? value : null;

            this.resetList();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;

            this.getList();
            this.updateRoute({
                page: this.page,
                limit: this.limit
            });
        },

        onListModeChange() {
            this.listMode = (this.listMode === 'grid') ? 'list' : 'grid';
            this.limit = (this.listMode === 'grid') ? 9 : 10;

            this.resetList();
        },

        onPreviewChange(theme) {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            this.showMediaModal = true;
            this.currentTheme = theme;
        },

        onPreviewImageRemove(theme) {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            theme.previewMediaId = null;
            theme.previewMedia = null;
            this.saveTheme(theme);
        },

        onModalClose() {
            this.showMediaModal = false;
            this.currentTheme = null;
        },

        onPreviewImageChange([image]) {
            this.currentTheme.previewMediaId = image.id;
            this.saveTheme(this.currentTheme);
            this.currentTheme.previewMedia = image;
        },

        saveTheme(theme) {
            this.isLoading = true;
            return this.themeRepository.save(theme, Shopware.Context.api).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getColumnConfig() {
            return [{
                property: 'name',
                label: this.$tc('sw-theme-manager.list.gridHeaderName'),
                primary: true
            },
            {
                property: 'salesChannels.length',
                label: this.$tc('sw-theme-manager.list.gridHeaderAssignment'),
                sortable: false,
            },
            {
                property: 'createdAt',
                label: this.$tc('sw-theme-manager.list.gridHeaderCreated')
            }];
        },

        deleteDisabledToolTip(theme) {
            return {
                showDelay: 300,
                message: this.$tc('sw-theme-manager.actions.deleteDisabledToolTip'),
                disabled: theme.salesChannels.length === 0
            };
        }
    }
});
