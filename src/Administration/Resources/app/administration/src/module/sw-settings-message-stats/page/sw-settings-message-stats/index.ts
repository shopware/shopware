import { defineComponent } from 'vue';
import type { MessageStatsResponse } from 'src/core/service/api/message-stats.api.service';
import type MessageStatsApiService from 'src/core/service/api/message-stats.api.service';
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
            statsResponse: null as MessageStatsResponse | null,
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
        statsData() {
            return this.statsResponse?.stats ?? null;
        },

        hasStats(): boolean {
            return (
                this.statsResponse?.enabled === true && this.statsData !== null && this.statsData.totalMessagesProcessed > 0
            );
        },

        isStatsDisabled(): boolean {
            return this.statsResponse?.enabled === false;
        },

        formattedProcessedSince(): string {
            if (!this.statsData?.processedSince) {
                return '';
            }
            return Shopware.Utils.format.date(this.statsData.processedSince, {
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
            });
        },

        formattedAverageTime(): string {
            if (!this.statsData?.averageTimeInQueue) {
                return '';
            }
            const formattedNumber = this.statsData.averageTimeInQueue.toFixed(2);
            return `${formattedNumber}${this.$tc('sw-settings-message-stats.general.seconds')}`;
        },

        statBlocks() {
            const emptyValue = '—';
            return [
                {
                    key: 'totalMessages',
                    label: this.$tc('sw-settings-message-stats.general.totalMessages'),
                    value: this.hasStats ? this.statsData?.totalMessagesProcessed : emptyValue,
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
            if (!this.statsData?.messageTypeStats) {
                return [];
            }

            return [...this.statsData.messageTypeStats].sort((a, b) => {
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
                this.statsResponse = await (this.messageStatsService as MessageStatsApiService).getStats();
            } catch (error) {
                const errorMessage =
                    error instanceof Error
                        ? error.message
                        : this.$t('global.notification.notificationLoadingDataErrorMessage');
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
