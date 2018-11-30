import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-tabs.html.twig';
import './sw-tabs.less';

/**
 * @public
 * @description Renders a tab navigation. Each tab item references a route and the tab content will be rendered
 * using <code>&lt;router-view&gt;</code> in the parent component.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-tabs>
 *     <sw-tabs-item title="General">
 *         General
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Product information">
 *         Product information
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Variants">
 *         Variants
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Properties">
 *         Properties
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Product images">
 *         Product images
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Advanced pricing">
 *         Advanced pricing
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item title="Sales analyses">
 *         Sales analyses
 *     </sw-tabs-item>
 * </sw-tabs>
 */
Component.register('sw-tabs', {
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'minimal'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'minimal'].includes(value);
            }
        }
    },

    data() {
        return {
            showArrowControls: false,
            scrollbarOffset: '',
            tabsClass: {
                [`sw-tabs__${this.variant}`]: this.variant
            }
        };
    },

    computed: {
        scrollbarOffsetStyle() {
            return {
                bottom: this.scrollbarOffset,
                'margin-top': this.scrollbarOffset
            };
        }
    },

    mounted() {
        this.initializeArrows();
        this.addScrollbarOffset();
    },

    methods: {
        onClickArrow(direction) {
            if (!['left', 'right'].includes(direction)) {
                return;
            }

            const tabsNavigation = this.$refs.swTabsNavigation;
            const tabsNavigationWidth = tabsNavigation.offsetWidth;

            if (direction === 'right') {
                tabsNavigation.scrollLeft += tabsNavigationWidth;
                return;
            }
            tabsNavigation.scrollLeft += -tabsNavigationWidth;
        },

        addScrollbarOffset() {
            const offset = dom.getScrollbarHeight(this.$refs.swTabsNavigation);

            this.scrollbarOffset = `-${offset}px`;
        },

        initializeArrows() {
            const tabsNavigation = this.$refs.swTabsNavigation;
            const tabsNavigationElements = this.$el.querySelectorAll('.sw-tabs-item');
            const tabsNavigationItems = Array.from(tabsNavigationElements);
            let tabsNavigationItemsWidth = 0;

            tabsNavigationItems.forEach((item) => {
                tabsNavigationItemsWidth += item.offsetWidth;
            });

            if (tabsNavigationItemsWidth > tabsNavigation.offsetWidth) {
                this.showArrowControls = true;
            }
        }
    }
});
