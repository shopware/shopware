/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type EntityCollection from 'src/core/data/entity-collection.data';
import template from './sw-settings-webhook-deliveries-modal.html.twig';
import './sw-settings-webhook-deliveries-modal.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['modal-close'],

    props: {
        webhook: {
            type: Object as PropType<Entity<'webhook'>>,
            required: true,
        },
    },

    data(): {
        isOpen: boolean;
        deliveries: EntityCollection<'webhook_event_log'> | null;
        hasLoadError: boolean;
        selectedDelivery: Entity<'webhook_event_log'> | null;
        activeTab: string;
    } {
        return {
            isOpen: true,
            deliveries: null,
            hasLoadError: false,
            selectedDelivery: null,
            activeTab: 'payload',
        };
    },

    computed: {
        eventLogRepository(): Repository<'webhook_event_log'> {
            return this.repositoryFactory.create('webhook_event_log');
        },

        hasNoDeliveries(): boolean {
            return this.deliveries?.length === 0;
        },

        deliveryColumns() {
            return [
                {
                    property: 'timestamp',
                    label: 'sw-settings-webhook.deliveries.columnSentAt',
                    primary: true,
                },
                {
                    property: 'deliveryStatus',
                    label: 'sw-settings-webhook.deliveries.columnStatus',
                },
                {
                    property: 'responseStatusCode',
                    label: 'sw-settings-webhook.deliveries.columnCode',
                },
                {
                    property: 'requestContent',
                    width: '240px',
                    label: 'sw-settings-webhook.deliveries.columnPayload',
                },
                {
                    property: 'responseContent',
                    width: '240px',
                    label: 'sw-settings-webhook.deliveries.columnResponse',
                },
                {
                    property: 'processingTime',
                    label: 'sw-settings-webhook.deliveries.columnDuration',
                    align: 'right',
                },
            ];
        },

        detailTabs() {
            return [
                {
                    label: this.$t('sw-settings-webhook.deliveries.tabPayload'),
                    name: 'payload',
                },
                {
                    label: this.$t('sw-settings-webhook.deliveries.tabResponse'),
                    name: 'response',
                },
            ];
        },

        activeContent(): string | null {
            if (this.activeTab === 'payload') {
                return this.format(this.selectedDelivery?.requestContent);
            }

            if (this.activeTab === 'response') {
                return this.format(this.selectedDelivery?.responseContent);
            }

            return null;
        },
    },

    created() {
        void this.loadDeliveries();
    },

    methods: {
        async loadDeliveries(): Promise<void> {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('webhookId', this.webhook.id));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.setTotalCountMode(0);

            try {
                this.deliveries = await this.eventLogRepository.search(criteria);
            } catch {
                this.hasLoadError = true;
            }
        },

        openDelivery(delivery: Entity<'webhook_event_log'>, tab: string): void {
            this.selectedDelivery = delivery;
            this.activeTab = tab;
        },

        sentAt(timestamp: number): Date {
            return new Date(timestamp * 1000);
        },

        body(content: unknown): string {
            const body = (content as { body?: unknown } | null)?.body;

            if (body === undefined || body === null) {
                return '';
            }

            return typeof body === 'string' ? body : JSON.stringify(body);
        },

        preview(content: unknown): string {
            const body = this.body(content).replace(/\s+/g, ' ').trim();

            return body.length > 30 ? `${body.slice(0, 30)}…` : body;
        },

        format(content: unknown): string {
            const body = this.body(content);

            try {
                return JSON.stringify(JSON.parse(body), null, 2);
            } catch {
                return body;
            }
        },

        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('modal-close');
            }
        },

        deliveryStatusVariant(status: string): string {
            return (
                (
                    {
                        success: 'positive',
                        failed: 'critical',
                        pending_retry: 'attention',
                    } as Record<string, string>
                )[status] ?? 'neutral'
            );
        },

        deliveryStatusLabel(status: string): string {
            return this.$t(`sw-settings-webhook.deliveries.status.${status}`);
        },
    },
});
