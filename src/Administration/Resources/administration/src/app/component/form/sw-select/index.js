import { Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import './sw-select.scss';
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
export default {
    name: 'sw-select',
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('sw-inline-snippet')
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
            default: ''
        },
        id: {
            type: String
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
            required: false,
            default: ''
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
            isLoading: false,
            isLoadingSelections: false,
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
                'sw-select--multi': this.multi,
                'is--searchable': this.showSearch
            };
        },
        selectId() {
            const id = (this.id) ? this.id : utils.createId();
            return `sw-select--${id}`;
        }
    },

    watch: {
        '$route.params.id'() {
            this.selections = [];
            this.results = [];
            this.loadSelections();
        },
        // load data of the selected option when it changes
        value() {
            if (!this.multi) {
                this.loadSelections();
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
            this.loadSelections();
            this.addEventListeners();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            this.$on('sw-select-option-clicked', this.addSelection);
            this.$on('sw-select-option-mouse-over', this.setActiveResultPosition);
            // Reload selections when global language changes
            this.$root.$on('on-change-application-language', this.loadSelections);
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);
        },


        removeEventListeners() {
            this.$root.$off('on-change-application-language', this.loadSelections);
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        loadSelections() {
            if (this.multi) {
                this.isLoadingSelections = true;

                this.associationStore.getList({
                    page: 1,
                    limit: 500 // ToDo: The concept of assigning a large amount of relations needs a special solution.
                }).then((response) => {
                    this.selections = response.items;
                    this.isLoadingSelections = false;
                });
            } else {
                // return if the value is not set yet(*note the watcher on value)
                if (!this.value) {
                    return;
                }
                if (this.valueEmitType === 'values') {
                    this.singleSelection = this.store.getById(this.value);
                    return;
                }

                if (this.valueEmitType === 'entities') {
                    this.singleSelection = this.store.getById(this.value.id);
                }
            }
        },

        loadResults() {
            this.isLoading = true;

            this.store.getList({
                page: 1,
                limit: this.resultsLimit,
                term: this.searchTerm,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.items;
                // Reset active position index after search
                this.setActiveResultPosition({ index: 0 });
                this.scrollToResultsTop();
                // Finish loading after next render tick
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });
        },

        loadPreviewResults() {
            this.isLoading = true;
            this.results = [];

            const params = {
                page: 1,
                limit: this.previewResultsLimit,
                criteria: this.criteria
            };
            if (this.sortField) {
                params.sortBy = this.sortField;
                params.sortDirection = 'ASC';
            }

            this.store.getList(params).then((response) => {
                // Abort if a search is done atm
                if (this.searchTerm !== '') {
                    return;
                }
                this.results = response.items;
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });
        },

        openResultList() {
            if (this.isExpanded === false) {
                this.loadPreviewResults();
            }
            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        closeResultList() {
            this.$nextTick(() => {
                this.isExpanded = false;
            });

            this.activeResultPosition = 0;
            this.searchTerm = '';

            if (!this.showSearch) {
                return;
            }

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
            this.$refs.swSelect.querySelector('.sw-select__results').scrollTop = 0;
        },

        setFocus() {
            if (this.multi) {
                this.$refs.swSelectInput.focus();
                return;
            }

            this.openResultList();

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
                    this.onDismissSelection(item);
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

            this.updateValue();

            this.closeResultList();
        },

        onKeyUpEnter() {
            this.$emit('sw-select-on-keyup-enter', this.activeResultPosition);
        },

        onDismissSelection(item) {
            this.dismissSelection(item);
            this.setFocus();
        },

        dismissSelection(item) {
            if (!item[this.itemValueKey]) {
                return;
            }

            this.selections = this.selections.filter((entry) => entry[this.itemValueKey] !== item[this.itemValueKey]);

            this.emitChanges(this.selections);

            if (this.defaultItemId && this.defaultItemId === item[this.itemValueKey]) {
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

            const lastSelection = this.selections[this.selections.length - 1];
            this.dismissSelection(lastSelection);
        },

        emitChanges(items) {
            const itemIds = items.map((item) => item.id);
            const associationStore = this.associationStore;

            // Delete existing relations
            Object.keys(associationStore.store).forEach((id) => {
                if (!itemIds.includes(id)) {
                    // Only delete the entity if we have a real entity proxy with delete function
                    // this is not the case if we are working with a local store
                    if (typeof associationStore.store[id].delete === 'function') {
                        associationStore.store[id].delete();
                        return;
                    }

                    associationStore.remove(associationStore.store[id]);
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
};
