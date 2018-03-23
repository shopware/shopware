import { Component } from 'src/core/shopware';
import template from './sw-tabs.html.twig';
import './sw-tabs.less';

Component.register('sw-tabs', {
    template,

    data() {
        return {
            showArrowControls: false
        };
    },

    mounted() {
        this.initializeArrows();
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
