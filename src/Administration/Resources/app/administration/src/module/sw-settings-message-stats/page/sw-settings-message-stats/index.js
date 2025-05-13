/**
 * @sw-package framework
 */
import template from './sw-settings-message-stats.html.twig';
import './sw-settings-message-stats.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['messageStatsService'],

    data() {
        return {
            isLoading: false,
            stats: null,
            columns: [
                {
                    property: 'type',
                    label: 'sw-settings-message-stats.general.type',
                    allowResize: true,
                    primary: true,
                    width: '70%',
                },
                {
                    property: 'count',
                    label: 'sw-settings-message-stats.general.count',
                    allowResize: true,
                    width: '30%',
                    align: 'right',
                },
            ],
        };
    },

    computed: {
        hasStats() {
            return this.stats !== null && this.stats.totalMessagesProcessed > 0;
        },

        formattedProcessedSince() {
            if (!this.stats?.processedSince) {
                return '';
            }
            return Shopware.Utils.format.date(this.stats.processedSince, {
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadStats();
        },

        async loadStats() {
            this.isLoading = true;
            try {
                this.stats = await this.messageStatsService.getStats();
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('sw-settings-message-stats.general.errorTitle'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
