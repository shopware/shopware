import { Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import './sw-single-select.scss';
import template from './sw-single-select.html.twig';

export default {
    name: 'sw-single-select',
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        options: {
            required: true,
            type: [Array, Object]
        },
        value: {
            required: true
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        label: {
            type: String,
            default: ''
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        valueProperty: {
            type: String,
            required: false,
            default: 'value'
        },
        keyProperty: {
            type: String,
            required: false,
            default: 'key'
        },
        required: {
            type: Boolean,
            required: false,
            default: false
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        showSearch: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            searchTerm: '',
            isExpanded: false,
            activeResultPosition: 1,
            isLoading: false,
            hasError: false,
            singleSelection: null,
            currentOptions: []
        };
    },

    computed: {
        selectClasses() {
            return {
                'has--error': !this.isValid || this.hasError,
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded,
                'is--searchable': this.showSearch
            };
        },
        selectId() {
            return `sw-single-select--${utils.createId()}`;
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
            this.currentOptions = this.options;
            this.loadSelected();
        },

        destroyedComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            this.$on('sw-single-select-option-clicked', this.setValue);
            this.$on('sw-single-select-option-mouse-over', this.setActiveResultPosition);
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keyup', this.closeOnClickOutside);
        },

        removeEventListeners() {
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keyup', this.closeOnClickOutside);
        },

        loadSelected() {
            this.resolveKey(this.value).then((item) => {
                this.singleSelection = item;
            });
        },

        resolveKey(key) {
            const found = this.currentOptions.find((item) => {
                return (item[this.keyProperty] === key);
            });

            return new Promise((resolve) => {
                resolve(found);
            });
        },

        search() {
            this.$emit('sw-single-select-search-term-change', this.searchTerm);
        },

        unsetValue() {
            this.singleSelection = null;
            this.updateInputElement();
        },

        updateInputElement() {
            if (this.singleSelection === null) {
                this.$emit('input', null);
                return;
            }

            this.$emit('input', this.singleSelection[this.keyProperty]);
        },

        isSelected(item) {
            if (this.singleSelection === null) {
                return false;
            }
            return this.singleSelection[this.keyProperty] === item[this.keyProperty];
        },

        setValue({ item }) {
            if (item === undefined || !item[this.keyProperty]) {
                return;
            }

            item = JSON.parse(JSON.stringify(item));
            if (item[this.valueProperty].constructor === String) {
                item[this.valueProperty] = item[this.valueProperty].replace(/<[^>]+>/g, '');
            }

            this.singleSelection = item;

            this.updateInputElement();
            this.closeResultList();
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
                this.scrollToResultsTop();
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

            this.$nextTick(() => {
                if (!this.$refs.swSelectInput) {
                    return;
                }

                this.$refs.swSelectInput.blur();
            });
        },

        setFocus(event) {
            this.openResultList(event);

            if (!this.showSearch) {
                return;
            }
            /*
             * since the input is not visible at first we need to wait a tick until the
             * result list with the input is visible
             */
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

            if (target.closest('.sw-single-select') !== this.$refs.swSelect) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
            }
        },

        setActiveResultPosition({ index }) {
            this.activeResultPosition = index;
            this.emitActiveResultPosition();
        },

        emitActiveResultPosition() {
            this.$emit('sw-single-select-active-item-index', this.activeResultPosition);
        },

        navigateUpResults() {
            this.$emit('sw-single-select-on-arrow-up', this.activeResultPosition);

            if (this.activeResultPosition === 0) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition - 1 });

            const swSelectEl = this.$refs.swSelect;
            const resultItem = swSelectEl.querySelector('.sw-single-select-option');
            const resultContainer = swSelectEl.querySelector('.sw-single-select__results');

            if (!resultItem) {
                return;
            }

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        navigateDownResults() {
            this.$emit('sw-single-select-on-arrow-down', this.activeResultPosition);

            if (this.activeResultPosition === this.currentOptions.length - 1 || this.currentOptions.length < 1) {
                return;
            }

            this.setActiveResultPosition({ index: this.activeResultPosition + 1 });

            const swSelectEl = this.$refs.swSelect;
            const activeItem = swSelectEl.querySelector('.is--active');
            const itemHeight = swSelectEl.querySelector('.sw-single-select-option').offsetHeight;


            if (!activeItem) {
                return;
            }

            const activeItemPosition = activeItem ? activeItem.offsetTop + itemHeight : 0;
            const resultContainer = swSelectEl.querySelector('.sw-single-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        onScroll(event) {
            this.$emit('scroll', event);
        },

        scrollToResultsTop() {
            this.setActiveResultPosition({ index: 0 });

            if (!this.$refs.swSelect.querySelector('.sw-single-select__results')) {
                return;
            }

            this.$refs.swSelect.querySelector('.sw-single-select__results').scrollTop = 0;
        },

        onKeyUpEnter() {
            this.$emit('sw-single-select-on-keyup-enter', this.activeResultPosition);
        }
    }
};
