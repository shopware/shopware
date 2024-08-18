import template from './sw-select-result-list.html.twig';
import './sw-select-result-list.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Base component for rendering result lists.
 * @example-type code-only
 */
Component.register('sw-select-result-list', {
    template,

    compatConfig: Shopware.compatConfig,

    provide() {
        return {
            setActiveItemIndex: this.setActiveItemIndex,
        };
    },

    inject: ['feature'],

    emits: [
        'item-select',
        'active-item-change',
        'outside-click',
        'paginate',
        'item-select-by-keyboard',
    ],

    props: {
        options: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        emptyMessage: {
            type: String,
            required: false,
            default: null,
        },

        focusEl: {
            type: [HTMLDocument, HTMLElement],
            required: false,
            default() { return document; },
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        popoverClasses: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        popoverResizeWidth: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            activeItemIndex: 0,
        };
    },

    computed: {
        emptyMessageText() {
            return this.emptyMessage || this.$tc('global.sw-select-result-list.messageNoResults');
        },

        popoverClass() {
            return [...this.popoverClasses, 'sw-select-result-list-popover-wrapper'];
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            this.addEventListeners();
        },

        mountedComponent() {
            // Set first item active
            this.emitActiveItemIndex();
        },

        beforeDestroyedComponent() {
            this.removeEventListeners();
        },

        setActiveItemIndex(index) {
            this.activeItemIndex = index;
            this.emitActiveItemIndex();
        },

        addEventListeners() {
            this.focusEl.addEventListener('keydown', this.navigate);
            document.addEventListener('click', this.checkOutsideClick);

            Shopware.Utils.EventBus.on('item-select', this.onItemSelect);
        },

        removeEventListeners() {
            this.focusEl.removeEventListener('keydown', this.navigate);
            document.removeEventListener('click', this.checkOutsideClick);

            Shopware.Utils.EventBus.off('item-select', this.onItemSelect);
        },

        onItemSelect(item) {
            this.$emit('item-select', item);
        },

        emitActiveItemIndex() {
            this.$emit('active-item-change', this.activeItemIndex);
            Shopware.Utils.EventBus.emit('active-item-change', this.activeItemIndex);
        },

        /**
         *
         * @param event {Event}
         */
        checkOutsideClick(event) {
            event.stopPropagation();

            const popoverContentClicked = this.$refs.popoverContent?.contains(event.target);
            const componentClicked = this.$el.contains(event.target);
            const parentClicked = this.$parent.$parent.$el.contains(event.target);

            if (popoverContentClicked || componentClicked || parentClicked) {
                return;
            }

            this.$emit('outside-click');
        },

        navigate({ key }) {
            key = key.toUpperCase();
            if (key === 'ARROWDOWN') {
                this.navigateNext();
                return;
            }

            if (key === 'ARROWUP') {
                this.navigatePrevious();
                return;
            }

            if (key === 'ENTER') {
                this.emitClicked();
            }
        },

        navigateNext() {
            if (this.activeItemIndex >= this.options.length - 1) {
                this.$emit('paginate');
                return;
            }

            this.activeItemIndex += 1;

            this.emitActiveItemIndex();
            this.updateScrollPosition();
        },

        navigatePrevious() {
            if (this.activeItemIndex > 0) {
                this.activeItemIndex -= 1;
            }

            this.emitActiveItemIndex();
            this.updateScrollPosition();
        },

        updateScrollPosition() {
            // wait until the new active item is rendered and has the active class
            this.$nextTick(() => {
                const resultContainer = document.querySelector('.sw-select-result-list__content');
                const activeItem = resultContainer.querySelector('.is--active');
                const itemHeight = activeItem.offsetHeight;
                const activeItemPosition = activeItem.offsetTop;
                const actualScrollTop = resultContainer.scrollTop;

                if (activeItemPosition === 0) {
                    return;
                }

                // Check if we need to scroll down
                if (resultContainer.offsetHeight + actualScrollTop < activeItemPosition + itemHeight) {
                    resultContainer.scrollTop += itemHeight;
                }

                // Check if we need to scroll up
                if (actualScrollTop !== 0 && activeItemPosition - actualScrollTop - itemHeight <= 0) {
                    resultContainer.scrollTop -= itemHeight;
                }
            });
        },

        emitClicked() {
            // This emit is subscribed in the sw-result component. They can for example be disabled and need
            // choose on their own if they are selected
            this.$emit('item-select-by-keyboard', this.activeItemIndex);
            Shopware.Utils.EventBus.emit('item-select-by-keyboard', this.activeItemIndex);
        },

        onScroll(event) {
            if (this.getBottomDistance(event.target) > 0) {
                return;
            }

            this.$emit('paginate');
        },

        getBottomDistance(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        },
    },
});
