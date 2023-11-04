/**
 * @package system-settings
 */
import template from './sw-settings-search-excluded-search-terms.html.twig';
import './sw-settings-search-excluded-search-terms.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'excludedSearchTermService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        searchConfigs: {
            type: Object,
            required: false,
            default: null,
        },

        isExcludedTermsLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            originalItems: [],
            showEmptyState: false,
            page: 1,
            limit: 10,
            total: 0,
            searchTerm: '',
            isLoading: false,
            isAddingItem: false,
            responseMessage: '',
        };
    },

    computed: {
        searchRepository() {
            return this.repositoryFactory.create('product_search_config');
        },

        getSearchableGeneralColumns() {
            return [{
                property: 'value',
                label: 'sw-settings-search.generalTab.textColumnSearchTerm',
                inlineEdit: 'string',
                sortable: false,
            }];
        },
    },

    watch: {
        searchConfigs() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (
                this.searchConfigs.excludedTerms === undefined ||
                this.searchConfigs.excludedTerms === null ||
                this.searchConfigs.excludedTerms.length <= 0
            ) {
                this.resetData();
                this.showEmptyState = true;
                this.isLoading = false;
                return;
            }
            this.originalItems = this.searchConfigs.excludedTerms;
            this.renderComponent();
        },

        resetData() {
            this.originalItems = [];
            this.items = [];
            this.page = 1;
            this.total = 0;
        },

        addExcludedSearchTerms() {
            this.showEmptyState = false;
        },

        onInsertTerm() {
            this.isAddingItem = true;
            this.searchTerm = '';
            this.$refs.itemFilter.term = '';
            this.page = 1;
            this.renderComponent();

            this.items.unshift({ id: null, value: '' });
            this.$refs.dataGrid.onDbClickCell(this.items[0]);
            this.$emit('edit-change', true);
        },

        renderComponent() {
            if (this.originalItems.length <= 0) {
                this.isLoading = false;
                this.items = [];
                return;
            }
            const all = this.filterItems();
            this.total = all.length;
            if (this.total <= 0) {
                this.items = [];
                this.showEmptyState = false;
                this.isLoading = false;
                return;
            }

            this.items = this.sliceItems(all);
            if (this.items.length <= 0) {
                this.page -= 1;
                this.renderComponent();
            }
            this.showEmptyState = false;
            this.isLoading = false;
        },

        filterItems() {
            return this.originalItems.filter((term) => {
                return term.search(this.searchTerm) >= 0;
            });
        },

        sliceItems(items) {
            const offset = (this.page - 1) * this.limit;
            return items.slice(offset, offset + this.limit).map((item, index) => {
                return { id: index, value: item };
            });
        },

        onPagePagination(params) {
            this.page = params.page;
            this.limit = params.limit;
            this.isAddingItem = false;
            this.renderComponent();
        },

        onDeleteExcludedTerm(terms) {
            this.responseMessage = this.$tc('sw-settings-search.notification.deleteExcludedTermSuccess');
            this.isLoading = true;
            const values = terms.filter((term) => { return term.value !== ''; }).map(term => term.value);
            if (values.length <= 0) {
                this.renderComponent();
                return;
            }
            this.originalItems = this.originalItems.filter((item) => {
                return !values.find(term => term === item);
            });
            this.saveConfig();
        },

        onSearchTermChange(searchTerm) {
            this.page = 1;
            this.searchTerm = searchTerm;
            if (searchTerm === '' && this.isAddingItem) {
                return;
            }
            this.isAddingItem = false;
            this.renderComponent();
        },

        selectionChanged(selection) {
            this.selection = selection;
        },

        onSaveEdit(term) {
            this.isLoading = true;
            // Make sure value is not null
            if (term.value === '') {
                this.createNotificationError({
                    message: this.$tc('sw-settings-search.notification.excludedTermRequired'),
                });
                this.renderComponent();
                return;
            }

            // Make sure value not exists
            const originTerm = this.getOriginItem(term);
            const isExists = this.originalItems.find((item) => {
                return item === term.value && item !== originTerm;
            });
            if (isExists) {
                this.createNotificationError({
                    message: this.$tc('sw-settings-search.notification.excludedTermAlreadyExists'),
                });
                this.renderComponent();
                return;
            }

            if (this.isAddingItem) {
                this.responseMessage = this.$tc('sw-settings-search.notification.createExcludedTermSuccess');
                this.originalItems.unshift(term.value);
                this.saveConfig();
                return;
            }

            this.responseMessage = this.$tc('sw-settings-search.notification.updateExcludedTermSuccess');
            this.originalItems[term.id] = term.value;
            this.saveConfig();
        },

        getOriginItem(term) {
            const all = this.filterItems();
            const items = this.sliceItems(all);
            const found = items.find(item => item.id === term.id);

            if (found) {
                return found.value;
            }
            return null;
        },

        onCancelEdit() {
            this.renderComponent();
            this.$emit('edit-change', false);
        },

        onBulkDeleteExcludedTerm() {
            this.onDeleteExcludedTerm(Object.values(this.selection));
        },

        saveConfig() {
            this.searchConfigs.excludedTerms = this.originalItems;

            return this.searchRepository.save(this.searchConfigs)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.responseMessage,
                    });
                    this.isAddingItem = false;
                    this.renderComponent();
                    this.$emit('edit-change', false);
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onResetExcludedSearchTermDefault() {
            this.excludedSearchTermService.resetExcludedSearchTerm()
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.resetToDefaultExcludedTermSuccess'),
                    });
                    this.$emit('data-load');
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.resetToDefaultExcludedTermError'),
                    });
                });
        },
    },
};
