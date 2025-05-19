import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';
import { MtText } from '@shopware-ag/meteor-component-library';
const { Criteria } = Shopware.Data;

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
            salesChannels: []
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
