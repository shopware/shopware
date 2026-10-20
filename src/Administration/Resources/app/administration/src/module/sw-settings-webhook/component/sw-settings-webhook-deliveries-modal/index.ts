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
        selectedDelivery: Entity<'webhook_event_log'> | null;
        activeTab: string;
    } {
        return {
            isOpen: true,
            deliveries: null,
            selectedDelivery: null,
            activeTab: 'payload',
        };
    },

    computed: {
        eventLogRepository(): Repository<'webhook_event_log'> {
            return this.repositoryFactory.create('webhook_event_log');
        },

        deliveryColumns() {
            return [
                {
                    property: 'createdAt',
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

        formattedPayload(): string {
            return this.format(this.selectedDelivery?.requestContent);
        },

        formattedResponse(): string {
            return this.format(this.selectedDelivery?.responseContent);
        },
    },

    created() {
        void this.loadDeliveries();
    },

    methods: {
        async loadDeliveries(): Promise<void> {
            // the event log has no webhook id, so deliveries are matched by name (and app for app webhooks)
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('webhookName', this.webhook.name));
            if (this.webhook.app) {
                criteria.addFilter(Criteria.equals('appName', this.webhook.app.name));
            }
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.deliveries = await this.eventLogRepository.search(criteria);
        },

        openDelivery(delivery: Entity<'webhook_event_log'>, tab: string): void {
            this.selectedDelivery = delivery;
            this.activeTab = tab;
        },

        // request and response content are stored as { headers, body }, with the body as the raw string that was sent
        body(content: unknown): string {
            const body = (content as { body?: unknown } | null)?.body;

            return typeof body === 'string' ? body : '';
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
