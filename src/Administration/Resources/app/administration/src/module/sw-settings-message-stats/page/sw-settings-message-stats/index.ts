import { defineComponent } from 'vue';
import type { MessageStats } from 'src/core/service/api/message-stats.api.service';
import template from './sw-settings-message-stats.html.twig';
import './sw-settings-message-stats.scss';

const { Mixin } = Shopware;

interface Column {
    property: string;
    label: string;
    align?: string;
}

/**
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    inject: ['messageStatsService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            stats: null as MessageStats | null,
            columns: [
                {
                    property: 'count',
                    label: 'sw-settings-message-stats.general.count',
                    align: 'right',
                },
                {
                    property: 'type',
                    label: 'sw-settings-message-stats.general.type',
                },
            ] as Column[],
        };
    },

    computed: {
        hasStats(): boolean {
            return this.stats !== null && this.stats.totalMessagesProcessed > 0;
        },

        formattedProcessedSince(): string {
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

        formattedAverageTime(): string {
            if (!this.stats?.averageTimeInQueue) {
                return '';
            }
            const formattedNumber = this.stats.averageTimeInQueue.toFixed(2);
            return `${formattedNumber}${this.$tc('sw-settings-message-stats.general.seconds')}`;
        },

        statBlocks() {
            const emptyValue = '—';
            return [
                {
                    key: 'totalMessages',
                    label: this.$tc('sw-settings-message-stats.general.totalMessages'),
                    value: this.hasStats ? this.stats?.totalMessagesProcessed : emptyValue,
                    tooltip: this.$tc('sw-settings-message-stats.general.totalMessagesHelp'),
                },
                {
                    key: 'averageTime',
                    label: this.$tc('sw-settings-message-stats.general.averageTime'),
                    value: this.hasStats ? this.formattedAverageTime : emptyValue,
                    tooltip: this.$tc('sw-settings-message-stats.general.averageTimeHelp'),
                },
                {
                    key: 'processingWindow',
                    label: this.$tc('sw-settings-message-stats.general.processingWindow'),
                    value: this.hasStats ? this.formattedProcessedSince : emptyValue,
                    tooltip: this.$tc('sw-settings-message-stats.general.processingWindowHelp'),
                },
            ];
        },

        sortedMessageTypeStats() {
            if (!this.stats?.messageTypeStats) {
                return [];
            }

            return [...this.stats.messageTypeStats].sort((a, b) => {
                return b.count - a.count;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            void this.loadStats();
        },

        async loadStats() {
            this.isLoading = true;
            try {
                this.stats = await this.messageStatsService.getStats();
            } catch (error) {
                let errorMessage: string;
                if (error instanceof Error) {
                    errorMessage = error.message;
                } else {
                    errorMessage = this.$t('global.notification.notificationLoadingDataErrorMessage');
                }
                this.createNotificationError({
                    title: this.$tc('sw-settings-message-stats.general.errorTitle'),
                    message: errorMessage,
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
