import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';
import { MtText } from '@shopware-ag/meteor-component-library';
import { PopoverRoot, PopoverTrigger, PopoverPortal, PopoverContent, RovingFocusItem, RovingFocusGroup } from 'reka-ui';
const { Criteria } = Shopware.Data;
import { motion } from 'motion-v';

const MODULES = [
    {
        id: 'dashboard',
        name: 'Dashboard',
        icon: 'dashboard',
        to: 'sw.dashboard.index',
        match(route) {
            return route.name === 'sw.dashboard.index' ? 'exact' : 'none';
        },
    },
    {
        id: 'products',
        name: 'Products',
        icon: 'tag',
        to: 'sw.product.index',
        match(route) {
            if (route.name.startsWith('sw.product.stream')) {
                return 'none';
            }

            return route.name.startsWith('sw.product') ? 'exact' : 'none';
        },
        children: [
            {
                id: 'reviews',
                name: 'Reviews',
                to: 'sw.review.index',
                match(route) {
                    return route.name.startsWith('sw.review') ? 'exact' : 'none';
                },
            },
            {
                id: 'categories',
                name: 'Categories',
                to: 'sw.category.index',
                match(route) {
                    return route.name.startsWith('sw.category') ? 'exact' : 'none';
                },
            },
            {
                id: 'dynamic-product-groups',
                name: 'Dynamic Product Groups',
                to: 'sw.product.stream.index',
                match(route) {
                    return route.name.startsWith('sw.product.stream') ? 'exact' : 'none';
                },
            },
            {
                id: 'properties',
                name: 'Properties',
                to: 'sw.property.index',
                match(route) {
                    return route.name.startsWith('sw.property') ? 'exact' : 'none';
                },
            },
            {
                id: 'manufacturers',
                name: 'Manufacturers',
                to: 'sw.manufacturer.index',
                match(route) {
                    return route.name.startsWith('sw.manufacturer') ? 'exact' : 'none';
                },
            },
        ],
    },
    {
        id: 'orders',
        name: 'Orders',
        icon: 'shopping-bag',
        to: 'sw.order.index',
        match(route) {
            return route.name.startsWith('sw.order') ? 'exact' : 'none';
        },
    },
    {
        id: 'customers',
        name: 'Customers',
        icon: 'users',
        to: 'sw.customer.index',
        match(route) {
            return route.name.startsWith('sw.customer') ? 'exact' : 'none';
        },
    },
    {
        id: 'content',
        name: 'Content',
        icon: 'image-text',
        to: 'sw.cms.index',
        match(route) {
            return route.name.startsWith('sw.cms') ? 'exact' : 'none';
        },
        children: [
            {
                id: 'themes',
                name: 'Themes',
                to: 'sw.theme.manager.index',
                match(route) {
                    return route.name.startsWith('sw.theme.manager') ? 'exact' : 'none';
                },
            },
        ],
    },
    {
        id: 'marketing',
        name: 'Marketing',
        icon: 'megaphone',
        to: 'sw.promotion.v2.index',
        match(route) {
            return route.name.startsWith('sw.promotion.v2') ? 'exact' : 'none';
        },
        children: [
            {
                id: 'newsletter',
                name: 'Newsletter recipients',
                to: 'sw.newsletter.recipient.index',
                match(route) {
                    return route.name.startsWith('sw.newsletter.recipient') ? 'exact' : 'none';
                },
            },
        ],
    },
    {
        id: 'extensions',
        name: 'Extensions',
        icon: 'puzzle-piece',
        to: 'sw.extension.my-extensions.listing',
        match(route) {
            return route.name.startsWith('sw.extension.my-extensions') ? 'exact' : 'none';
        },
    },
    {
        id: 'settings',
        name: 'Settings',
        icon: 'cog',
        to: 'sw.settings.index',
        match(route) {
            return route.name.startsWith('sw.settings') ? 'exact' : 'none';
        },
    },
];

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    components: {
        MtText,
        PopoverRoot,
        PopoverContent,
        PopoverTrigger,
        PopoverPortal,
        RovingFocusGroup,
        RovingFocusItem,
        MotionDiv: motion.div,
        MotionUl: motion.ul,
    },

    inject: [
        'repositoryFactory',
        'menuService',
        'loginService',
    ],

    data() {
        return {
            showAccountMenu: false,
            isDarkMode: false,
            salesChannels: [],
            MODULES,
        };
    },

    watch: {
        isDarkMode: {
            handler(newValue) {
                // The code below disables all css transitions during the theme change
                //   See more: https://paco.me/writing/disable-theme-transitions
                const css = document.createElement('style');
                css.type = 'text/css';
                css.appendChild(
                    document.createTextNode(
                        `* {
   -webkit-transition: none !important;
   -moz-transition: none !important;
   -o-transition: none !important;
   -ms-transition: none !important;
   transition: none !important;
}`,
                    ),
                );
                document.head.appendChild(css);

                if (newValue) {
                    document.documentElement.dataset.theme = 'dark';
                } else {
                    document.documentElement.dataset.theme = 'light';
                }

                // Re-enables all css transitions
                const _ = window.getComputedStyle(css).opacity;
                document.head.removeChild(css);
            },
            immediate: true,
        },
    },

    created() {
        this.salesChannelRepository.search(this.salesChannelCriteria).then((response) => {
            this.salesChannels = response;
        });
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 7);

            criteria.addIncludes({
                sales_channel: [
                    'name',
                    'type',
                    'active',
                    'translated',
                    'domains',
                ],
                sales_channel_type: ['iconName'],
                sales_channel_domain: [
                    'url',
                    'languageId',
                ],
            });

            criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
            criteria.addAssociation('type');
            criteria.addAssociation('domains');

            return criteria;
        },
    },

    methods: {
        isSalesChannelSelected(salesChannelId) {
            const isSalesChannelRoute = this.$route.name?.startsWith('sw.sales.channel.');
            if (!isSalesChannelRoute) return false;

            return this.$route.params?.id === salesChannelId;
        },

        signOut() {
            this.loginService.logout();
            Shopware.Store.get('session').removeCurrentUser();
            Shopware.Store.get('notification').clearGrowlNotificationsForCurrentUser();
            Shopware.Store.get('notification').clearNotificationsForCurrentUser();

            this.$router.push({
                name: 'sw.login.index',
            });
        },
    },
};
