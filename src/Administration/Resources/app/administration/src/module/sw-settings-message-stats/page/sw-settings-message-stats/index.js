/**
 * @sw-package framework
 */
import template from './sw-settings-message-stats.html.twig';
import './sw-settings-message-stats.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-message-stats', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            stats: null,
            isBannerHidden: false,
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

        onCloseBanner() {
            this.isBannerHidden = true;
        },

        async loadStats() {
            this.isLoading = true;
            try {
                // Simulate API call
                await new Promise(resolve => setTimeout(resolve, 1000));
                this.stats = {
                    totalMessagesProcessed: 0,
                    averageTimeInQueue: 0,
                    processedSince: '2025-04-15T15:08:42.000+00:00',
                    messageTypeStats: [],
                };
                this.stats = {
                    totalMessagesProcessed: 127,
                    averageTimeInQueue: 11.17,
                    processedSince: '2025-04-15T15:08:42.000+00:00',
                    messageTypeStats: [
                        {
                            type: 'InvalidateCacheTask',
                            count: 123,
                        },
                        {
                            type: 'CreateAliasTask',
                            count: 45,
                        },
                        {
                            type: 'ProductExportGenerateTask',
                            count: 67,
                        },
                    ],
                };

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
});
