/**
 * @package admin
 */

// eslint-disable-next-line import/no-extraneous-dependencies
import template from './sw-tabs-deprecated.html.twig';
import './sw-tabs-deprecated.scss';


const { Component } = Shopware;
const util = Shopware.Utils;
const dom = Shopware.Utils.dom;

/**
 * @private
 * @description Renders tabs. Each item references a route or emits a custom event.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-tabs>
 *     <sw-tabs-item>
 *         Explore
 *     </sw-tabs-item>
 *     <sw-tabs-item>
 *         My Plugins
 *     </sw-tabs-item>
 * </sw-tabs>
 */
Component.register('sw-tabs-deprecated', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    provide() {
        return {
            onNewItemActive: this.registerOnNewItemActiveHandler,
            registerNewTabItem: this.registerNewTabItem,
            unregisterNewTabItem: this.unregisterNewTabItem,
            swTabsSetActiveItem: this.setActiveItem,
        };
    },

    extensionApiDevtoolInformation: {
        property: 'ui.tabs',
        positionId: (currentComponent) => currentComponent.positionIdentifier,
    },

    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },

        isVertical: {
            type: Boolean,
            required: false,
            default: false,
        },

        small: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        alignRight: {
            type: Boolean,
            required: false,
            default: false,
        },

        defaultItem: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            active: this.defaultItem || '',
            isScrollable: false,
            activeItem: null,
            scrollLeftPossible: false,
            scrollRightPossible: true,
            firstScroll: false,
            scrollbarOffset: '',
            hasRoutes: false,
            onNewItemActiveHandlers: [],
            registeredTabItems: [],
        };
    },

    computed: {
        tabClasses() {
            return {
                'sw-tabs--vertical': this.isVertical,
                'sw-tabs--small': this.small,
                'sw-tabs--scrollable': this.isScrollable,
                'sw-tabs--align-right': this.alignRight,
                'sw-tabs--scrollbar-active': this.scrollbarOffset > 0,
            };
        },

        arrowClassesLeft() {
            return {
                'sw-tabs__arrow--disabled': !this.scrollLeftPossible,
            };
        },

        arrowClassesRight() {
            return {
                'sw-tabs__arrow--disabled': !this.scrollRightPossible,
            };
        },

        sliderLength() {
            const children = Shopware.Utils.VueHelper.getCompatChildren();

            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                if (this.$children[this.activeItem]) {
                    const activeChildren = this.$children[this.activeItem];
                    return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
                }
            } else if (children[this.activeItem]) {
                const activeChildren = children[this.activeItem];
                return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
            }

            return 0;
        },

        activeTabHasErrors() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                return this.$children[this.activeItem]?.hasError ?? false;
            }

            return this.registeredTabItems[this.activeItem]?.hasError ?? false;
        },

        activeTabHasWarnings() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                return this.$children[this.activeItem]?.hasWarning ?? false;
            }

            return this.registeredTabItems[this.activeItem]?.hasWarning ?? false;
        },

        sliderClasses() {
            return {
                'has--error': this.activeTabHasErrors,
                'has--warning': !this.activeTabHasErrors && this.activeTabHasWarnings,
            };
        },

        sliderMovement() {
            const children = this.isCompatEnabled('INSTANCE_CHILDREN')
                ? this.$children
                : Shopware.Utils.VueHelper.getCompatChildren();

            if (children[this.activeItem]) {
                const activeChildren = children[this.activeItem];
                return this.isVertical ? activeChildren.$el.offsetTop : activeChildren.$el.offsetLeft;
            }

            return 0;
        },

        sliderStyle() {
            if (this.isVertical) {
                return `
                    transform: translate(0, ${this.sliderMovement}px) rotate(${this.alignRight ? '-90deg' : '90deg'});
                    width: ${this.sliderLength}px;
                `;
            }

            return `
                transform: translate(${this.sliderMovement}px, 0) rotate(0deg);
                width: ${this.sliderLength}px;
                bottom: ${this.scrollbarOffset}px;
            `;
        },

        tabContentStyle() {
            return {
                'padding-bottom': `${this.scrollbarOffset}px`,
            };
        },

        tabExtensions() {
            return Shopware.State.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },
    },

    watch: {
        '$route'() {
            this.updateActiveItem();
        },

        defaultItem() {
            this.active = this.defaultItem;
            this.updateActiveItem();
        },

        activeTabHasErrors() {
            this.recalculateSlider();
        },

        activeTabHasWarnings() {
            this.recalculateSlider();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.updateActiveItem();
        },

        mountedComponent() {
            const tabContent = this.$refs.swTabContent;

            /* Can't be a property in methods because otherwise the this context is not available
             */
            this.scrollEventHandler = util.throttle(() => {
                const rightEnd = tabContent.scrollWidth - tabContent.offsetWidth;
                const leftDistance = tabContent.scrollLeft;

                this.scrollRightPossible = !(rightEnd - leftDistance < 5);
                this.scrollLeftPossible = !(leftDistance < 5);
            }, 100);

            /* Can't be a property in methods because otherwise the this context is not available
             */
            this.tabContentMutationObserver = new MutationObserver(this.onTabBarResize);
            this.tabContentMutationObserver.observe(tabContent, {
                subtree: true,
                characterData: true,
                attributes: true,
            });

            tabContent.addEventListener('scroll', this.scrollEventHandler);

            this.checkIfNeedScroll();
            this.addScrollbarOffset();

            const that = this;
            this.$device.onResize({
                listener() {
                    that.checkIfNeedScroll();
                    that.addScrollbarOffset();
                },
                component: this,
            });
            this.recalculateSlider();

            if (this.$slots.default &&
                // Check direct child
                this.$slots.default({ active: this.active })?.[0]?.componentOptions?.propsData?.route
            ) {
                this.hasRoutes = true;
            }

            if (this.$slots.default &&
                // Check sub child
                this.$slots.default({ active: this.active })?.[0]?.children?.[0]?.componentOptions?.propsData?.route
            ) {
                this.hasRoutes = true;
            }
        },

        beforeDestroyComponent() {
            const tabContent = this.$refs.swTabContent;

            if (tabContent) {
                tabContent.removeEventListener('scroll', this.scrollEventHandler);
            }
            this.$device.removeResizeListener(this);

            if (this.tabContentMutationObserver) {
                this.tabContentMutationObserver.disconnect();
            }
        },

        registerOnNewItemActiveHandler(callback) {
            this.onNewItemActiveHandlers.push(callback);
        },

        registerNewTabItem(item) {
            this.registeredTabItems.push(item);
        },

        unregisterNewTabItem(item) {
            this.registeredTabItems = this.registeredTabItems.filter((registeredItem) => {
                return registeredItem !== item;
            });
        },

        onNewItemActiveHandler(callback) {
            this.onNewItemActiveHandlers.forEach((handler) => {
                handler(callback);
            });
        },

        onTabBarResize() {
            requestAnimationFrame(async () => {
                this.checkIfNeedScroll();
                this.addScrollbarOffset();
                this.recalculateSlider();
            });
        },

        recalculateSlider() {
            window.setTimeout(() => {
                const activeItem = this.activeItem;
                this.activeItem = null;
                this.activeItem = activeItem;
            }, 0);
        },

        updateActiveItem() {
            this.$nextTick().then(() => {
                if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                    const children = this.$children;

                    const firstActiveTabItem = children.find((child) => {
                        return child.$el.nodeType === 1 && child.$el.classList.contains('sw-tabs-item--active');
                    });

                    if (!firstActiveTabItem) {
                        return;
                    }

                    this.activeItem = children.indexOf(firstActiveTabItem);
                    if (!this.firstScroll) {
                        this.scrollToItem(firstActiveTabItem);
                    }
                    this.firstScroll = true;
                } else {
                    const firstActiveTabItem = this.registeredTabItems.find((child) => {
                        return child.$el.nodeType === 1 && child.$el.classList.contains('sw-tabs-item--active');
                    });

                    if (!firstActiveTabItem) {
                        return;
                    }

                    this.activeItem = this.registeredTabItems.indexOf(firstActiveTabItem);
                    if (!this.firstScroll) {
                        this.scrollToItem(firstActiveTabItem);
                    }
                    this.firstScroll = true;
                }
            });
        },

        scrollTo(direction) {
            if (!['left', 'right'].includes(direction)) {
                return;
            }

            const tabContent = this.$refs.swTabContent;
            const tabContentWidth = tabContent.offsetWidth;

            if (direction === 'right') {
                tabContent.scrollLeft += (tabContentWidth / 2);
                return;
            }
            tabContent.scrollLeft += -(tabContentWidth / 2);
        },

        checkIfNeedScroll() {
            const tabContent = this.$refs.swTabContent;

            if (!tabContent) {
                return;
            }

            this.isScrollable = tabContent.scrollWidth !== tabContent.offsetWidth;
        },

        setActiveItem(item) {
            this.$emit('new-item-active', item);
            this.onNewItemActiveHandler(item);
            this.active = item.name;
            this.updateActiveItem();
        },

        scrollToItem(item) {
            const tabContent = this.$refs.swTabContent;
            const tabContentWidth = tabContent.offsetWidth;
            const itemOffset = item.$el.offsetLeft;
            const itemWidth = item.$el.clientWidth;

            if ((tabContentWidth / 2) < itemOffset) {
                const scrollWidth = itemOffset - (tabContentWidth / 2) + (itemWidth / 2);
                tabContent.scrollLeft = scrollWidth;
            }
        },

        addScrollbarOffset() {
            if (!this.$refs.swTabContent) {
                return;
            }

            this.scrollbarOffset = dom.getScrollbarHeight(this.$refs.swTabContent);
        },
    },
});
