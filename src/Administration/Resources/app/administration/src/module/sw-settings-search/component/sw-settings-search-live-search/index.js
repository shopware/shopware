/**
 * @sw-package inventory
 */
import template from './sw-settings-search-live-search.html.twig';
import './sw-settings-search-live-search.scss';
import '../sw-settings-search-live-search-keyword';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'liveSearchService',
    ],

    emits: [
        'live-search-results-change',
        'sales-channel-change',
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
            productSortings: [],
            productSortingKey: null,
            liveSearchResults: null,
            searchInProgress: false,
            showExampleModal: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        productSortingRepository() {
            return this.repositoryFactory.create('product_sorting');
        },

        isSearchEnable() {
            return this.salesChannelId !== null;
        },

        searchColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-settings-search.liveSearchTab.labelName'),
                    rawData: true,
                },
                {
                    property: 'score',
                    label: this.$tc('sw-settings-search.liveSearchTab.labelScore'),
                    rawData: true,
                },
            ];
        },

        products() {
            return this.liveSearchResults && this.liveSearchResults.elements;
        },

        productSortingCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addSorting(Criteria.sort('priority', 'DESC'));
            return criteria;
        },

        searchParams() {
            const params = {
                salesChannelId: this.salesChannelId,
                search: this.liveSearchTerm,
            };

            if (this.productSortingKey) {
                params.order = this.productSortingKey;
            }

            return params;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannels();
            this.fetchProductSortings();
            this.liveSearchTerm = this.searchTerms;
            this.liveSearchResults = this.searchResults;
        },

        searchOnStorefront() {
            if (!this.liveSearchTerm.length) {
                return;
            }

            this.searchInProgress = true;

            this.liveSearchService
                .search(this.searchParams, {}, {}, { 'sw-language-id': Shopware.Context.api.languageId })
                .then((data) => {
                    this.liveSearchResults = data.data;
                    this.searchInProgress = false;
                    this.$emit('live-search-results-change', {
                        searchTerms: this.liveSearchTerm,
                        searchResults: this.liveSearchResults,
                    });
                })
                .catch((error) => {
                    const message =
                        error.response.status === 500
                            ? this.$tc('sw-settings-search.notification.notSupportedLanguageError')
                            : error.message;

                    this.createNotificationError({
                        message,
                    });
                })
                .finally(() => {
                    this.searchInProgress = false;
                });
        },

        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria(1, 25)).then((response) => {
                this.salesChannels = response;
            });
        },

        fetchProductSortings() {
            this.productSortingRepository.search(this.productSortingCriteria).then((response) => {
                this.productSortings = response;
                const topSearchSorting = this.productSortings.find((entry) => entry.key === 'score');

                if (topSearchSorting) {
                    this.productSortingKey = topSearchSorting.key;
                }
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
};
