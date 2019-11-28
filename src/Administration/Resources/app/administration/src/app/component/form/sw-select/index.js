import CriteriaFactory from 'src/core/factory/criteria.factory';
import './sw-select.scss';
import template from './sw-select.html.twig';

const { Component, Mixin } = Shopware;
const utils = Shopware.Utils;

/**
 * @public
 * @status deprecated 6.1
 * @example-type code-only
 * @component-example
 * // Single select
 * <sw-select id="language" label="Language" :store="languageStore"></sw-select>
 *
 * // Multi select
 * <sw-select multi id="language" label="Language" :store="languageStore" :associationStore="languageAssociationStore">
 * </sw-select>
 */
Component.register('sw-select', {
    template,

    mixins: [
        Mixin.getByName('validation')
    ],

    props: {
        multi: {
            type: Boolean,
            required: false,
            default: false
        },
        criteria: {
            type: Object,
            required: false,
            default: null
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        displayName: {
            type: String,
            required: false,
            default: 'name'
        },
        // Only required if this is a single select, multi select values are handled over the association store
        value: {
            required: false
        },
        hasPreview: {
            type: Boolean,
            required: false,
            default: true
        },
        label: {
            type: String,
            default: ''
        },
        id: {
            type: String
        },
        previewResultsLimit: {
            type: Number,
            required: false,
            default: 10
        },
        resultsLimit: {
            type: Number,
            required: false,
            default: 25
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        store: {
            type: Object,
            required: true
        },
        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: false
        },
        // Only required if this is a multi select
        associationStore: {
            type: Object,
            required: false
        },
        // For multi selects with a default value
        defaultItemId: {
            type: String,
            required: false
        },
        itemValueKey: {
            type: String,
            required: false,
            default: 'id'
        },
        helpText: {
            Type: String,
            required: false,
            default: ''
        },
        possibleMinPosition: {
            type: Number,
            required: false,
            default: 0
        },
        // In Single Selections
        showSearch: {
            type: Boolean,
            required: false,
            default: true
        },
        required: {
            type: Boolean,
            required: false,
            default: false
        },
        // Defines how the selections will be emitted as value
        valueEmitType: {
            type: String,
            required: false,
            default: 'values',
            validValues: [
                'values', // singleSelection -> the selected itemValueKey; multiSelect -> array of selected itemValueKeys
                'entity' // singleSelection -> the selected entity; multiSelect -> array of selected entities
            ],
            validator(value) {
                return ['values', 'entity'].includes(value);
            }
        },
        sortField: {
            type: String,
            required: false,
            default: null
        },
        sortDirection: {
            type: String,
            required: false,
            default: 'ASC'
        },
        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'small'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'small'].includes(value);
            }
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            results: [],
            // for multi select
            page: 1,
            total: 0,
            previewPage: 1,
            previewTotal: 0,
            nextLoadStep: this.resultsLimit,
            nextPreviewLoadStep: 0,
            // the current displayed selected items
            selections: [],
            selected: [],
            activeResultPosition: 1,
            isLoading: false,
            isLoadingSelections: false,
            deletedItems: [],
            // for a single selection
            singleSelection: {}
        };
    },

    computed: {
        selectClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded,
                'sw-select--multi': this.multi,
                'is--searchable': this.showSearch,
                'sw-select--small': this.size === 'small'
            };
        },

        hasError() {
            return !!this.$attrs.error;
        },

        selectId() {
            const id = (this.id) ? this.id : utils.createId();
            return `sw-select--${id}`;
        }
    },

    watch: {
        '$route.params.id'() {
            this.init();
        },
        // load data of the selected option when it changes
        value() {
            if (!this.multi) {
                this.init();
            }
        },
        // Show loading indicator while selected option is being fetched in single selections
        'singleSelection.isLoading': {
            handler(value) {
                if (!this.multi) {
                    this.isLoadingSelections = value;
                }
            }
        },
        // load data of the selected option when store changes
        store() {
            if (!this.multi) {
                this.init();
            }
        },
        associationStore() {
            this.init();
        },
        disabled() {
            if (this.disabled) {
                this.searchTerm = '';
            }
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.init();
            this.addEventListeners();
        },

        init() {
            this.selections = [];
            this.selected = [];
            this.results = [];
            this.deletedItems = [];
            this.loadSelected(true);
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            this.$on('option-click', this.addSelection);
            this.$on('option-mouse-over', this.setActiveResultPosition);
            // Reload selections when global language changes
            this.$root.$on('on-change-application-language', this.loadSelected);
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);
        },

        removeEventListeners() {
            this.$root.$off('on-change-application-language', this.loadSelected);
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        calculateNextPreviewLoadStep() {
            const nextStep = this.previewTotal - this.previewResultsLimit * this.previewPage;

            return nextStep > this.previewResultsLimit ? this.previewResultsLimit : nextStep;
        },

        calculateNextLoadStep() {
            const nextStep = this.total - this.resultsLimit * this.page;

            return nextStep > this.resultsLimit ? this.resultsLimit : nextStep;
        },

        getDistFromBottom(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        },

        loadMore() {
            this.page += 1;
            this.getList();
        },

        getList(clearList = false) {
            this.isLoading = true;

            this.store.getList(this.getListParams()).then((response) => {
                const ids = response.items.map((item) => {
                    return item[this.itemValueKey];
                });

                if (ids.length === 0 || this.selected.length >= this.previewTotal) {
                    this.applyList(response, { items: [] }, clearList);
                    return;
                }

                const criteria = CriteriaFactory.equalsAny(this.itemValueKey, ids);
                this.associationStore.getList({
                    page: 1,
                    limit: this.resultsLimit,
                    criteria
                }).then((secondResponse) => {
                    this.applyList(response, secondResponse, clearList);
                });
            });
        },

        applyList(response, secondResponse, clearList) {
            const result = secondResponse.items.filter((item) => {
                return this.deletedItems.every((x) => {
                    return x !== item[this.itemValueKey];
                });
            });

            this.selected = [...this.selected, ...result];

            if (clearList) {
                this.results = [];
            }

            if (this.highlightSearchTerm) {
                response.items.forEach((item) => {
                    if (item.translated && item.translated.hasOwnProperty(this.displayName)) {
                        item.translated[this.displayName] = this.highlight(item.translated[this.displayName]);
                    } else {
                        item[this.displayName] = this.highlight(item[this.displayName]);
                    }
                });
            }

            this.isLoading = false;

            this.$nextTick(() => {
                this.results = [...this.results, ...response.items];

                this.total = response.total;
                this.nextLoadStep = this.calculateNextLoadStep();

                if (clearList) {
                    this.scrollToResultsTop();
                    this.setActiveResultPosition({ index: 0 });
                }

                this.$emit('list-load', this.results);
            });
        },

        loadSelected(clearList = false) {
            if (this.multi) {
                this.isLoadingSelections = true;

                this.associationStore.getList(this.getPreviewListParams()).then((response) => {
                    if (clearList) {
                        this.selections = [];
                    }

                    this.selections = [...this.selections, ...response.items];
                    this.isLoadingSelections = false;
                    this.previewTotal = response.total;
                    this.nextPreviewLoadStep = this.calculateNextPreviewLoadStep();
                });
            } else {
                // return if the value is not set yet(*note the watcher on value)
                if (!this.value) {
                    this.singleSelection = {};
                    return;
                }
                if (this.valueEmitType === 'values') {
                    this.singleSelection = this.store.getById(this.value);
                    return;
                }

                if (this.valueEmitType === 'entities') {
                    this.singleSelection = this.store.getById(this.value[this.itemValueKey]);
                }
            }
        },

        resetListing() {
            this.isExpanded = false;
            this.page = 1;
            this.nextLoadStep = this.calculateNextLoadStep();
        },


        highlight(text) {
            if (this.searchTerm.trim().length <= 0) {
                return text;
            }

            return text.replace(
                new RegExp(this.searchTerm.trim(), 'gi'),
                `<span class='is--highlighted'>${this.searchTerm.trim()}</span>`
            );
        },

        getListParams() {
            return {
                sortBy: this.sortField,
                sortDirection: this.sortDirection,
                page: this.page,
                limit: this.resultsLimit,
                criteria: this.criteria,
                term: this.searchTerm
            };
        },

        getPreviewListParams() {
            return {
                sortBy: this.sortField,
                sortDirection: this.sortDirection,
                page: this.previewPage,
                limit: this.previewResultsLimit
            };
        },

        onScroll(event) {
            if (this.getDistFromBottom(event.target) !== 0) {
                return;
            }

            this.loadMore();
        },

        onPreviewLoadMore(event) {
            event.preventDefault();
            this.previewPage += 1;
            this.loadSelected(this.previewPage === 1);
        },

        openResultList(event) {
            if (event.relatedTarget && event.relatedTarget.type === 'submit') {
                this.$nextTick(() => {
                    if (!this.$refs.swSelectInput) {
                        return;
                    }

                    this.$refs.swSelectInput.blur();
                });
                return;
            }

            if (this.isExpanded === false) {
                this.getList(true);
                this.scrollToResultsTop();
            }

            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        closeResultList() {
            this.$nextTick(() => {
                this.resetListing();
            });

            this.activeResultPosition = 0;
            this.searchTerm = '';

            if (!this.showSearch) {
                return;
            }

            this.$nextTick(() => {
                if (!this.$refs.swSelectInput) {
                    return;
                }

                this.$refs.swSelectInput.blur();
            });
        },

        onSearchTermChange() {
            this.isLoading = true;
            this.page = 1;
            this.doGlobalSearch();

            this.$emit('search-term-change', this.searchTerm);
        },

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            this.getList(true);
        }, 400),

        setActiveResultPosition({ index }) {
            this.activeResultPosition = index;
            this.emitActiveResultPosition();
        },

        emitActiveResultPosition() {
            this.$emit('active-item-index-select', this.activeResultPosition);
        },

        navigateUpResults() {
            this.$emit('on-arrow-up', this.activeResultPosition);

            if (this.activeResultPosition === this.possibleMinPosition) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition - 1 });

            const swSelectEl = this.$refs.swSelect;
            const resultItem = swSelectEl.querySelector('.sw-select-option');
            const resultContainer = swSelectEl.querySelector('.sw-select__results');

            if (!resultItem) {
                return;
            }

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        navigateDownResults() {
            this.$emit('on-arrow-down', this.activeResultPosition);

            if (this.activeResultPosition === this.results.length - 1 || this.results.length < 1) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition + 1 });

            const swSelectEl = this.$refs.swSelect;
            const activeItem = swSelectEl.querySelector('.is--active');
            const itemHeight = swSelectEl.querySelector('.sw-select-option').offsetHeight;


            if (!activeItem) {
                return;
            }

            const activeItemPosition = activeItem ? activeItem.offsetTop + itemHeight : 0;
            const resultContainer = swSelectEl.querySelector('.sw-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        scrollToResultsTop() {
            this.setActiveResultPosition({ index: 0 });

            if (!this.$refs.swSelect.querySelector('.sw-select__results')) {
                return;
            }

            this.$refs.swSelect.querySelector('.sw-select__results').scrollTop = 0;
        },

        setFocus(event) {
            if (this.disabled) {
                this.closeResultList();
                return;
            }

            if (this.multi) {
                this.$refs.swSelectInput.focus();
                return;
            }

            this.openResultList(event);

            if (!this.showSearch) {
                return;
            }
            // since the input is not visible at first we need to wait a tick until the
            // result list with the input is visible
            this.$nextTick(() => {
                if (!this.$refs.swSelectInput) {
                    return;
                }

                this.$refs.swSelectInput.focus();
            });
        },

        closeOnClickOutside(event) {
            if (event.type === 'keyup' && event.key && event.key.toLowerCase() !== 'tab') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-select') !== this.$refs.swSelect) {
                this.resetListing();
                this.activeResultPosition = 0;
            }
        },

        isInSelections(item) {
            if (this.multi) {
                return !this.selected.every((selection) => {
                    return selection[this.itemValueKey] !== item[this.itemValueKey];
                });
            }

            return this.singleSelection[this.itemValueKey] === item[this.itemValueKey];
        },

        addSelection({ item }) {
            if (item === undefined || !item[this.itemValueKey]) {
                return;
            }

            item = JSON.parse(JSON.stringify(item));
            if (item[this.displayName] && typeof item[this.displayName] === 'string') {
                item[this.displayName] = item[this.displayName].replace(/<[^>]+>/g, '');
            }

            if (item.translated && item.translated.hasOwnProperty(this.displayName)) {
                item.translated[this.displayName] = item.translated[this.displayName].replace(/<[^>]+>/g, '');
            }

            if (this.multi) {
                if (this.isInSelections(item)) {
                    this.onDismissSelection(item[this.itemValueKey]);
                    return;
                }

                this.deletedItems = this.deletedItems.filter((identifier) => {
                    return identifier !== item[this.itemValueKey];
                });

                this.selections.push(item);
                this.selected.push(item);
                this.searchTerm = '';

                this.emitChanges(this.selections);

                this.setFocus();

                if (this.selections.length === 1) {
                    this.changeDefaultItemId(item[this.itemValueKey]);
                }
                return;
            }

            this.singleSelection = item;

            this.updateValue();
            this.closeResultList();
        },

        onKeyUpEnter() {
            this.$emit('on-keyup-enter', this.activeResultPosition);
        },

        onDismissSelection(identifier) {
            this.dismissSelection(identifier);
            this.setFocus();
        },

        dismissSelection(identifier) {
            if (!identifier) {
                return;
            }

            this.deletedItems.push(identifier);
            this.selections = this.selections.filter((entry) => entry[this.itemValueKey] !== identifier);
            this.selected = this.selected.filter((entry) => entry[this.itemValueKey] !== identifier);

            this.emitChanges(this.selections);

            if (this.defaultItemId && this.defaultItemId === identifier) {
                if (this.selections.length >= 1) {
                    this.changeDefaultItemId(this.selections[0][this.itemValueKey]);
                } else {
                    this.changeDefaultItemId(null);
                }
            }
        },

        dismissLastSelection() {
            if (this.searchTerm.length > 0) {
                return;
            }

            if (!this.selections.length) {
                return;
            }

            const lastSelection = this.selections[this.selections.length - 1];
            this.dismissSelection(lastSelection[this.itemValueKey]);
        },

        emitChanges(items) {
            const itemIds = items.map((item) => item[this.itemValueKey]);
            const associationStore = this.associationStore;

            // Delete existing relations
            this.deletedItems.forEach((identifier) => {
                const itemId = Object.keys(associationStore.store).find((id) => {
                    return associationStore.store[id][this.itemValueKey] === identifier;
                });

                if (!itemIds.includes(identifier) &&
                    associationStore.store[itemId] &&
                    typeof associationStore.store[itemId].delete === 'function'
                ) {
                    associationStore.store[itemId].delete();
                    return;
                }

                if (associationStore.store[itemId]) {
                    associationStore.remove(associationStore.store[itemId]);
                }
            });

            // Add new relations
            items.forEach((item) => {
                if (!associationStore.store[item.id]) {
                    associationStore.add(item);
                }

                // In case the entity was already created but was deleted before
                associationStore.store[item.id].isDeleted = false;
            });

            this.updateValue();
        },

        updateValue() {
            const values = [];
            if (this.valueEmitType === 'values') {
                if (this.multi) {
                    this.selections.forEach((selection) => {
                        values.push(selection[this.itemValueKey]);
                    });
                } else {
                    values.push(this.singleSelection[this.itemValueKey]);
                }
            } else if (this.valueEmitType === 'entity') {
                if (this.multi) {
                    values.push(...this.selections);
                } else {
                    values.push(this.singleSelection);
                }
            }

            if (values.length < 1) {
                this.$emit('input', null);
                return;
            }

            if (values.length === 1 && !this.multi) {
                this.$emit('input', values.shift());
                return;
            }

            this.$emit('input', values);
        },

        changeDefaultItemId(id) {
            if (typeof this.defaultItemId !== 'undefined') {
                this.$emit('default_changed', id);
            }
        },

        onClickIndicatorDismiss() {
            if (this.multi) {
                this.selections = [];
            }

            this.singleSelection = {};

            this.updateValue();
        }
    }
});
