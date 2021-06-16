import template from './sw-select-result-list.html.twig';
import './sw-select-result-list.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @description Base component for rendering result lists.
 * @example-type code-only
 */
Component.register('sw-select-result-list', {
    template,

    provide() {
        return {
            setActiveItemIndex: this.setActiveItemIndex,
        };
    },

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

    beforeDestroy() {
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
        },

        removeEventListeners() {
            this.focusEl.removeEventListener('keydown', this.navigate);
            document.removeEventListener('click', this.checkOutsideClick);
        },

        emitActiveItemIndex() {
            this.$emit('active-item-change', this.activeItemIndex);
        },

        /**
         *
         * @param event {Event}
         */
        checkOutsideClick(event) {
            event.stopPropagation();

            const popoverContentClicked = this.$refs.popoverContent.contains(event.target);
            const componentClicked = this.$el.contains(event.target);
            const parentClicked = this.$parent.$el.contains(event.target);

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
        },

        onScroll(event) {
            if (this.getBottomDistance(event.target) !== 0) {
                return;
            }

            this.$emit('paginate');
        },

        getBottomDistance(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        },
    },
});
