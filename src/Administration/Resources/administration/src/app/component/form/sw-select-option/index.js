import './sw-select-option.scss';
import template from './sw-select-option.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-select-option',
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
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--active': this.isActive,
                'is--disabled': this.disabled
            };
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
        },

        destroyedComponent() {
            this.removeEvents();
        },

        registerEvents() {
            this.$parent.$on('sw-select-active-item-index', this.checkActiveState);
            this.$parent.$on('sw-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        removeEvents() {
            this.$parent.$off('sw-select-active-item-index', this.checkActiveState);
            this.$parent.$off('sw-select-on-keyup-enter', this.selectOptionOnEnter);
        },

        emitActiveResultPosition(originalDomEvent, index) {
            this.$emit({ originalDomEvent, index });
        },

        onClicked(originalDomEvent) {
            if (this.disabled) {
                return;
            }

            this.$parent.$emit('sw-select-option-clicked', {
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

        isInSelections(item) {
            return this.$parent.isInSelections(item);
        },

        onMouseEnter(originalDomEvent) {
            this.$parent.$emit('sw-select-option-mouse-over', { originalDomEvent, index: this.index });
            this.isActive = true;
        }
    }
};
