import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-tabs.html.twig';
import './sw-tabs.less';

Component.register('sw-tabs', {
    template,

    data() {
        return {
            showArrowControls: false,
            scrollbarOffset: ''
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
