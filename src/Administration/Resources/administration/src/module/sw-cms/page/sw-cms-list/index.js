import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-cms-list.html.twig';
import './sw-cms-list.scss';

Component.register('sw-cms-list', {
    template,

    mixins: [
        Mixin.getByName('listing')
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
            criteria: null
        };
    },

    computed: {
        pageStore() {
            return State.getStore('cms_page');
        },

        languageStore() {
            return State.getStore('language');
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
        }
    }
});
