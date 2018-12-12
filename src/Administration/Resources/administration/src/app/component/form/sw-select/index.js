import { Component, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import './sw-select.less';
import template from './sw-select.html.twig';

/**
 * @public
 * @status ready
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
        value: {
            required: true
        },
        label: {
            type: String,
            default: ''
        },
        id: {
            type: String,
            required: true
        },
        previewResultsLimit: {
            type: Number,
            required: false,
            default: 25
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
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            results: [],
            // for multi select
            selections: [],
            activeResultPosition: 0,
            isLoading: true,
            hasError: false,
            // for a single selection
            singleSelection: {}
        };
    },

    computed: {
        selectClasses() {
            return {
                'has--error': !this.isValid || this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded,
                'sw-select--multi': this.multi
            };
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },

        disabled: 'loadPreviewResults',
        criteria: 'loadPreviewResults',
        // load data of the selected option when it changes
        value(value) {
            if (typeof value === 'string') {
                this.loadSelections();
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
            this.selections = [];
            this.results = [];

            if (!this.disabled) {
                this.loadPreviewResults();
            }
            this.loadSelections();
            this.addEventListeners();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            this.$on('sw-select-option-clicked', this.addSelection);
            this.$on('sw-select-option-mouse-over', this.setActiveResultPosition);
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);
        },


        removeEventListeners() {
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        loadSelections() {
            this.isLoading = true;

            if (this.multi) {
                this.associationStore.getList({
                    page: 1,
                    limit: 500 // ToDo: The concept of assigning a large amount of relations needs a special solution.
                }).then((response) => {
                    this.selections = response.items;
                    this.isLoading = false;
                });
            } else {
                // return if the value is not set yet(*note the watcher on value)
                if (!this.value) {
                    return;
                }
                this.singleSelection = this.store.getById(this.value);
                this.isLoading = false;
            }
        },

        loadResults() {
            this.store.getList({
                page: 1,
                limit: this.resultsLimit,
                term: this.searchTerm,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.items;
                this.isLoading = false;
                // Reset active position index after search
                this.setActiveResultPosition({ index: 0 });

                this.scrollToResultsTop();
            });
        },

        loadPreviewResults() {
            this.store.getList({
                page: 1,
                limit: this.previewResultsLimit,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.items;
                this.isLoading = false;
            });
        },

        openResultList() {
            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        closeResultList() {
            this.$nextTick(() => {
                this.isExpanded = false;
            });
            this.$refs.swSelectInput.blur();
        },

        onSearchTermChange() {
            this.isLoading = true;

            this.doGlobalSearch();
        },

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            if (this.searchTerm.length > 0) {
                this.loadResults();
            } else {
                this.loadPreviewResults();
                this.scrollToResultsTop();
            }
        }, 400),

        setActiveResultPosition({ index }) {
            this.activeResultPosition = index;
            this.emitActiveResultPosition();
        },

        emitActiveResultPosition() {
            this.$emit('sw-select-active-item-index', this.activeResultPosition);
        },

        navigateUpResults() {
            if (this.activeResultPosition === 0) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition - 1 });

            const swSelectEl = this.$refs.swSelect;
            const resultItem = swSelectEl.querySelector('.sw-select-option');
            const resultContainer = swSelectEl.querySelector('.sw-select__results');

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        navigateDownResults() {
            if (this.activeResultPosition === this.results.length - 1 || this.results.length < 1) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition + 1 });

            const swSelectEl = this.$refs.swSelect;
            const activeItem = swSelectEl.querySelector('.is--active');
            const itemHeight = swSelectEl.querySelector('.sw-select-option').offsetHeight;
            const activeItemPosition = activeItem.offsetTop + itemHeight;
            const resultContainer = swSelectEl.querySelector('.sw-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        scrollToResultsTop() {
            this.setActiveResultPosition({ index: 0 });
            this.$refs.swSelect.querySelector('.sw-select__results').scrollTop = 0;
        },

        setFocus() {
            if (this.multi) {
                this.$refs.swSelectInput.focus();
                return;
            }

            this.openResultList();
            // since the input is not visible at first we need to wait a tick until the
            // result list with the input is visible
            this.$nextTick(() => {
                this.$refs.swSelectInput.focus();
            });
        },

        closeOnClickOutside(event) {
            if (event.type === 'keyup' && event.key.toLowerCase() !== 'tab') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-select') !== this.$refs.swSelect) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
            }
        },

        isInSelections(item) {
            if (this.multi) {
                return !this.selections.every((selection) => {
                    return selection[this.itemValueKey] !== item[this.itemValueKey];
                });
            }

            return this.singleSelection[this.itemValueKey] === item[this.itemValueKey];
        },

        addSelection({ item }) {
            if (item === undefined || !item[this.itemValueKey]) {
                return;
            }

            if (this.multi) {
                if (this.isInSelections(item)) {
                    return;
                }

                this.selections.push(item);
                this.searchTerm = '';

                this.emitChanges(this.selections);

                this.setFocus();

                if (this.selections.length === 1) {
                    this.changeDefaultItemId(item[this.itemValueKey]);
                }
                return;
            }

            this.singleSelection = item;

            this.$emit('input', item[this.itemValueKey]);
            this.closeResultList();
        },

        onKeyUpEnter() {
            this.$emit('sw-select-on-keyup-enter', this.activeResultPosition);
        },

        onDismissSelection(id) {
            this.dismissSelection(id);
            this.setFocus();
        },

        dismissSelection(id) {
            if (!id) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry.id !== id);

            this.emitChanges(this.selections);

            if (this.defaultItemId && this.defaultItemId === id) {
                if (this.selections.length >= 1) {
                    this.changeDefaultItemId(this.selections[0].id);
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

            const lastSelectionId = this.selections[this.selections.length - 1].id;

            this.dismissSelection(lastSelectionId);
        },

        emitChanges(items) {
            const itemIds = items.map((item) => item.id);
            const associationStore = this.associationStore;

            // Delete existing relations
            Object.keys(associationStore.store).forEach((id) => {
                if (!itemIds.includes(id)) {
                    associationStore.store[id].delete();
                }
            });

            // Add new relations
            items.forEach((item) => {
                if (!associationStore.store[item.id]) {
                    associationStore.create(item.id, item, true);
                }

                // In case the entity was already created but was deleted before
                associationStore.store[item.id].isDeleted = false;
            });

            this.$emit('input', this.selections);
        },

        changeDefaultItemId(id) {
            if (typeof this.defaultItemId !== 'undefined') {
                this.$emit('default_changed', id);
            }
        }
    }
});
