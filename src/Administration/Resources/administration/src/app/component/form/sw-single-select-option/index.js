import './sw-single-select-option.scss';
import template from './sw-single-select-option.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-single-select-option',
    template,

    props: {
        index: {
            type: Number,
            required: true
        },
        item: {
            type: Object,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        selected: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    computed: {
        componentClasses() {
            return [
                {
                    'is--active': this.isActive,
                    'is--disabled': this.disabled
                },
                `sw-single-select-option--${this.index}`
            ];
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
            this.registerEvents();

            if (this.index === 0) {
                this.isActive = true;
            }
        },

        destroyedComponent() {
            this.removeEvents();
        },

        registerEvents() {
            this.$parent.$on('sw-single-select-active-item-index', this.checkActiveState);
            this.$parent.$on('sw-single-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        removeEvents() {
            this.$parent.$off('sw-single-select-active-item-index', this.checkActiveState);
            this.$parent.$off('sw-single-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        emitActiveResultPosition(originalDomEvent, index) {
            this.$emit({ originalDomEvent, index });
        },

        onClicked(originalDomEvent) {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('sw-single-select-option-clicked', {
                originalDomEvent,
                item: this.item
            });
        },

        checkActiveState(index) {
            if (index === this.index) {
                this.isActive = true;
                return;
            }
            this.isActive = false;
        },

        selectOptionOnEnter(index) {
            if (index !== this.index) {
                return;
            }

            this.onClicked({});
        },

        isSelected(item) {
            return this.$parent.isSelected(item);
        },

        onMouseEnter(originalDomEvent) {
            this.$parent.$emit('sw-single-select-option-mouse-over', { originalDomEvent, index: this.index });
            this.isActive = true;
        }
    }
};
