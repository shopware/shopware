import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';
import { MtText } from '@shopware-ag/meteor-component-library';
const { Criteria } = Shopware.Data;

const MODULES = [
    {
        id: 'dashboard',
        name: 'Dashboard',
        icon: 'dashboard',
        to: 'sw.dashboard.index',
    },
    {
        id: 'products',
        name: 'Products',
        icon: 'tag',
        to: 'sw.product.index',
    },
    {
        id: 'orders',
        name: 'Orders',
        icon: 'shopping-bag',
        to: 'sw.order.index'
    },
    {
        id: 'customers',
        name: 'Customers',
        icon: 'users',
        to: 'sw.customer.index'
    },
    {
        id: 'content',
        name: 'Content',
        icon: 'image-text',
        to: 'sw.cms.index'
    },
    {
        id: 'marketing',
        name: 'Marketing',
        icon: 'megaphone',
        to: 'sw.promotion.v2.index'
    },
    {
        id: 'extensions',
        name: 'Extensions',
        icon: 'puzzle-piece',
        to: 'sw.extension.my-extensions.listing'
    },
    {
        id: 'settings',
        name: 'Settings',
        icon: 'cog',
        to: 'sw.settings.index'
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
    },

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            isDarkMode: false,
            salesChannels: [],
            MODULES
        };
    },

    watch: {
        isDarkMode: {
            handler(newValue) {
                if (newValue)  {
                    document.documentElement.dataset.theme = 'dark';
                } else {
                    document.documentElement.dataset.theme = 'light';
                }
            },
            immediate: true
        }
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
    }
};
