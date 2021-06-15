import template from './sw-settings-search-live-search.html.twig';
import './sw-settings-search-live-search.scss';
import '../sw-settings-search-live-search-keyword';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-search-live-search', {
    template,

    inject: [
        'repositoryFactory',
        'liveSearchService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        currentSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        searchTerms: {
            type: String,
            required: false,
            default: null,
        },

        searchResults: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            liveSearchTerm: '',
            salesChannels: [],
            salesChannelId: this.currentSalesChannelId,
            liveSearchResults: null,
            searchInProgress: false,
            showExampleModal: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isSearchEnable() {
            return this.salesChannelId !== null;
        },

        searchColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-settings-search.liveSearchTab.labelName'),
                rawData: true,
            }, {
                property: 'score',
                label: this.$tc('sw-settings-search.liveSearchTab.labelScore'),
                rawData: true,
            }];
        },

        products() {
            return this.liveSearchResults && this.liveSearchResults.elements;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannels();
            this.liveSearchTerm = this.searchTerms;
            this.liveSearchResults = this.searchResults;
        },

        searchOnStorefront() {
            if (!this.liveSearchTerm.length) {
                return;
            }

            this.searchInProgress = true;
            this.liveSearchService.search({
                salesChannelId: this.salesChannelId,
                search: this.liveSearchTerm,
            }, {}, {}, { 'sw-language-id': Shopware.Context.api.languageId }).then((data) => {
                this.liveSearchResults = data.data;
                this.searchInProgress = false;
                this.$emit('live-search-results-change', {
                    searchTerms: this.liveSearchTerm,
                    searchResults: this.liveSearchResults,
                });
            }).catch((error) => {
                const message = error.response.status === 500
                    ? this.$tc('sw-settings-search.notification.notSupportedLanguageError')
                    : error.message;

                this.createNotificationError({
                    message,
                });
            }).finally(() => {
                this.searchInProgress = false;
            });
        },

        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria()).then((response) => {
                this.salesChannels = response;
            });
        },

        changeSalesChannel(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.$emit('sales-channel-change', salesChannelId);
        },

        onShowExampleModal() {
            this.showExampleModal = true;
        },

        onCloseExampleModal() {
            this.showExampleModal = false;
        },
    },
});
