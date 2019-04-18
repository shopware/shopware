import { Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import './sw-multi-select.scss';
import template from './sw-multi-select.html.twig';

export default {
    name: 'sw-multi-select',
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        options: {
            type: Array,
            required: true
        },
        labelProperty: {
            type: String,
            required: false,
            default: 'value'
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'key'
        },
        value: {
            required: false
        },
        label: {
            type: String,
            default: ''
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        required: {
            type: Boolean,
            required: false,
            default: false
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
        valueLimit: {
            type: Number,
            required: false,
            default: 10
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            currentOptions: [],
            currentValue: [],
            visibleValues: [],
            invisibleValueCount: 0,
            limit: this.valueLimit,
            isLoading: false
        };
    },

    computed: {
        selectClasses() {
            return {
                'has--error': !this.isValid || this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded,
                'sw-multi-select--multi': true,
                'is--searchable': this.showSearch
            };
        },
        selectId() {
            return `sw-multi-select--${utils.createId()}`;
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    watch: {
        'options'() {
            this.initData();
        },
        'value'() {
            this.visibleValues = [];
            this.initData();
        }
    },

    methods: {
        createdComponent() {
            this.initData();

            this.addEventListeners();
        },

        initData() {
            this.currentOptions = this.options;
            this.currentValue = this.value;
            this.invisibleValueCount = this.value.length;

            this.loadVisibleItems();
        },

        loadVisibleItems() {
            const toShow = [];

            const visibleKeys = this.visibleValues.map((item) => {
                return item[this.valueProperty];
            });

            this.currentValue.forEach((item) => {
                if (toShow.length >= this.limit) {
                    return false;
                }

                if (!visibleKeys.includes(item)) {
                    toShow.push(item);
                }
                return true;
            });

            const items = this.resolveKeys(toShow);
            this.visibleValues.push(...items);

            this.invisibleValueCount -= this.limit;
        },

        search() {
            if (this.searchTerm.length <= 0) {
                this.currentOptions = this.options;
                return;
            }

            this.currentOptions = this.options.filter((item) => {
                const value = item[this.labelProperty];

                return value.toLowerCase().includes(this.searchTerm.toLowerCase());
            });
        },

        isSelected(item) {
            return !this.currentValue.every((key) => {
                return key !== item[this.valueProperty];
            });
        },

        addItem({ item }) {
            if (item === undefined || !item[this.valueProperty]) {
                return;
            }

            const key = item[this.valueProperty];

            if (this.isSelected(item)) {
                this.remove(key);
                return;
            }

            this.currentValue.push(key);
            this.visibleValues.push(item);

            this.updateValue();
            this.setFocus();
        },

        remove(identifier) {
            // remove identifier from key list
            this.currentValue = this.currentValue.filter((item) => {
                return item !== identifier;
            });

            // remove identifier from visible element list
            this.visibleValues = this.visibleValues.filter((item) => {
                return item[this.valueProperty] !== identifier;
            });

            if (this.visibleValues.length <= 0) {
                this.loadVisibleItems();
            }

            this.updateValue();
            this.setFocus();
        },

        removeLastItem() {
            if (this.searchTerm.length > 0) {
                return;
            }

            if (!this.visibleValues.length) {
                return;
            }

            const lastSelection = this.visibleValues[this.visibleValues.length - 1];
            this.remove(lastSelection[this.valueProperty]);
        },

        resolveKeys(keys) {
            const values = [];

            keys.forEach((key) => {
                this.options.forEach((item) => {
                    if (key === item[this.valueProperty]) {
                        values.push(item);
                    }
                });
            });

            return values;
        },

        expandValues(event) {
            event.preventDefault();

            this.loadVisibleItems();
        },

        updateValue() {
            if (this.currentValue.length < 1) {
                this.$emit('input', null);
                return;
            }

            this.$emit('input', this.currentValue);
        },

        onScroll(event) {
            this.$emit('scroll', event);
        },

        onSearchTermChange() {
            this.page = 1;
            this.searchDelayed();

            this.$emit('sw-multi-select-search-term-change', this.searchTerm);
        },

        onKeyUpEnter() {
            this.$emit('sw-multi-select-on-keyup-enter', this.activeResultPosition);
        },

        searchDelayed: utils.debounce(function debouncedSearch() {
            this.search();
        }, 400),

        openResultList(event) {
            if (event.relatedTarget && event.relatedTarget.type === 'submit') {
                this.$refs.swSelectInput.blur();
                return;
            }

            if (this.isExpanded === false) {
                this.scrollToResultsTop();
            }

            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        closeResultList() {
            this.$nextTick(() => {
                this.page = 1;
                this.isExpanded = false;
            });

            this.activeResultPosition = 0;

            if (!this.showSearch) {
                return;
            }

            this.$refs.swSelectInput.blur();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            this.$on('sw-multi-select-option-clicked', this.addItem);
            this.$on('sw-multi-select-option-mouse-over', this.setActiveResultPosition);
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);
        },

        removeEventListeners() {
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        setFocus() {
            this.$refs.swSelectInput.focus();
        },

        setActiveResultPosition({ index }) {
            this.activeResultPosition = index;
            this.emitActiveResultPosition();
        },

        emitActiveResultPosition() {
            this.$emit('sw-multi-select-active-item-index', this.activeResultPosition);
        },

        navigateUpResults() {
            this.$emit('sw-multi-select-on-arrow-up', this.activeResultPosition);

            if (this.activeResultPosition === this.possibleMinPosition) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition - 1 });

            const swSelectEl = this.$refs.swSelect;
            const resultItem = swSelectEl.querySelector('.sw-multi-select-option');
            const resultContainer = swSelectEl.querySelector('.sw-multi-select__results');

            if (!resultItem) {
                return;
            }

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        navigateDownResults() {
            this.$emit('sw-multi-select-on-arrow-down', this.activeResultPosition);

            if (this.activeResultPosition === this.currentOptions.length - 1 || this.currentOptions.length < 1) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition + 1 });

            const swSelectEl = this.$refs.swSelect;
            const activeItem = swSelectEl.querySelector('.is--active');
            const itemHeight = swSelectEl.querySelector('.sw-multi-select-option').offsetHeight;

            if (!activeItem) {
                return;
            }

            const activeItemPosition = activeItem ? activeItem.offsetTop + itemHeight : 0;
            const resultContainer = swSelectEl.querySelector('.sw-multi-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        scrollToResultsTop() {
            this.setActiveResultPosition({ index: 0 });

            if (!this.$refs.swSelect.querySelector('.sw-multi-select__results')) {
                return;
            }

            this.$refs.swSelect.querySelector('.sw-multi-select__results').scrollTop = 0;
        },

        closeOnClickOutside(event) {
            if (event.type === 'keyup' && event.key && event.key.toLowerCase() !== 'tab') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-multi-select') !== this.$refs.swSelect) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
            }
        }
    }
};
