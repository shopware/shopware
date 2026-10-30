/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

function child(wrapper: VueWrapper, name: string): VueWrapper<ComponentPublicInstance> {
    return wrapper.findComponent({ name }) as VueWrapper<ComponentPublicInstance>;
}

type Delivery = {
    id: string;
    deliveryStatus: string;
    timestamp?: number;
    processingTime?: number;
    requestContent?: { body?: string };
    responseContent?: { body?: unknown };
    responseReasonPhrase?: string;
};

const delivered: Delivery = {
    id: 'delivered',
    deliveryStatus: 'success',
    timestamp: 1767225600,
    processingTime: 2,
    requestContent: { body: '{"event":"order.placed","data":{"payload":[{"id":"order-1","orderNumber":"10000"}]}}' },
    responseContent: { body: { received: true } },
};

const failed: Delivery = {
    id: 'failed',
    deliveryStatus: 'failed',
    requestContent: { body: 'not json' },
    responseContent: { body: '' },
    responseReasonPhrase: 'Service Unavailable',
};

const rejected: Delivery = {
    id: 'rejected',
    deliveryStatus: 'failed',
    processingTime: 0,
    requestContent: { body: '{}' },
    responseContent: { body: 'Bad Request' },
};

const queued: Delivery = {
    id: 'queued',
    deliveryStatus: 'queued',
};

function createCollection<T>(items: T[]) {
    return Object.assign([...items], { total: items.length });
}

async function createWrapper({
    webhook = { id: 'webhook-1', name: 'Order placed' },
    deliveries = [
        delivered,
        failed,
        rejected,
        queued,
    ] as Delivery[],
    search = jest.fn(() => Promise.resolve(createCollection(deliveries))),
} = {}) {
    const wrapper = mount(await wrapTestComponent('sw-settings-webhook-deliveries-modal', { sync: true }), {
        props: { webhook },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ search }),
                },
            },
            stubs: {
                'mt-modal-root': {
                    name: 'mt-modal-root',
                    emits: ['change'],
                    template: '<div class="mt-modal-root"><slot></slot></div>',
                },
                'mt-modal': {
                    template: `
                        <div class="mt-modal">
                            <slot name="default"></slot>
                            <div class="mt-modal__footer"><slot name="footer"></slot></div>
                        </div>`,
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['defaultItem'],
                    emits: ['new-item-active'],
                    template: '<div class="mt-tabs"></div>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                        <div class="sw-data-grid">
                            <div v-for="item in dataSource" :class="'row-' + item.id">
                                <div class="sent"><slot name="column-timestamp" v-bind="{ item }"></slot></div>
                                <div class="status"><slot name="column-deliveryStatus" v-bind="{ item }"></slot></div>
                                <div class="payload"><slot name="column-requestContent" v-bind="{ item }"></slot></div>
                                <div class="response"><slot name="column-responseContent" v-bind="{ item }"></slot></div>
                                <div class="duration"><slot name="column-processingTime" v-bind="{ item }"></slot></div>
                            </div>
                        </div>`,
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="mt-badge" :data-variant="variant"><slot></slot></span>',
                },
                'sw-time-ago': {
                    props: ['date'],
                    template: '<span class="sw-time-ago">{{ date.toISOString() }}</span>',
                },
                'mt-empty-state': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, search };
}

describe('src/module/sw-settings-webhook/component/sw-settings-webhook-deliveries-modal', () => {
    it('loads the latest deliveries of the webhook by its id', async () => {
        const { search } = await createWrapper();

        expect(search).toHaveBeenCalledTimes(1);
        const criteria = (search.mock.calls[0] as unknown as [CriteriaType])[0];
        expect(criteria.limit).toBe(25);
        expect(criteria.filters).toEqual([expect.objectContaining({ field: 'webhookId', value: 'webhook-1' })]);
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'createdAt', order: 'DESC' })]);
    });

    it('shows the status of each delivery', async () => {
        const { wrapper } = await createWrapper({
            deliveries: [
                { id: 'success', deliveryStatus: 'success' },
                { id: 'failed', deliveryStatus: 'failed' },
                { id: 'retry', deliveryStatus: 'pending_retry' },
                { id: 'queued', deliveryStatus: 'queued' },
            ],
        });

        const status = (id: string) => wrapper.find(`.row-${id} .status .mt-badge`);

        expect(status('success').text()).toBe('sw-settings-webhook.deliveries.status.success');
        expect(status('success').attributes('data-variant')).toBe('positive');
        expect(status('failed').attributes('data-variant')).toBe('critical');
        expect(status('retry').attributes('data-variant')).toBe('attention');
        expect(status('queued').attributes('data-variant')).toBe('neutral');
    });

    it('shows when the last attempt of a delivery started', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-delivered .sent').text()).toBe('2026-01-01T00:00:00.000Z');
        expect(wrapper.find('.row-queued .sent').text()).toBe('');
    });

    it('shows no payload link for a delivery that was not attempted yet', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-queued .payload a').exists()).toBe(false);
    });

    it('shows a short preview of the payload and the response', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-delivered .payload').text()).toBe('{"event":"order.placed","data"…');
        expect(wrapper.find('.row-delivered .response').text()).toBe('{"received":true}');
    });

    it('shows the duration of a delivery in seconds, also when it took none', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-delivered .duration').text()).toBe('sw-settings-webhook.deliveries.durationSeconds');
        expect(wrapper.find('.row-rejected .duration').text()).toBe('sw-settings-webhook.deliveries.durationSeconds');
        expect(wrapper.find('.row-failed .duration').text()).toBe('');
    });

    it('shows the reason phrase when the response has no body', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-failed .response a').exists()).toBe(false);
        expect(wrapper.find('.row-failed .response').text()).toBe('Service Unavailable');
    });

    it('opens the formatted payload of a delivery and goes back to the list', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.row-delivered .payload a').trigger('click');

        expect(wrapper.find('.sw-data-grid').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('payload');
        expect(wrapper.find('pre').text()).toBe(
            JSON.stringify({ event: 'order.placed', data: { payload: [{ id: 'order-1', orderNumber: '10000' }] } }, null, 2),
        );

        child(wrapper, 'mt-tabs').vm.$emit('new-item-active', 'response');
        await flushPromises();

        expect(wrapper.find('pre').text()).toBe(JSON.stringify({ received: true }, null, 2));

        await wrapper.find('.mt-modal__footer button').trigger('click');

        expect(wrapper.find('pre').exists()).toBe(false);
        expect(wrapper.find('.sw-data-grid').exists()).toBe(true);
    });

    it('opens the response of a delivery and shows a body that is no JSON as it is', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.row-rejected .response a').trigger('click');

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('response');
        expect(wrapper.find('pre').text()).toBe('Bad Request');
    });

    it('shows neither payload nor response on a tab added by an extension', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.row-delivered .payload a').trigger('click');
        child(wrapper, 'mt-tabs').vm.$emit('new-item-active', 'extension-tab');
        await flushPromises();

        expect(wrapper.find('pre').exists()).toBe(false);
    });

    it('shows the empty state when the webhook has no deliveries', async () => {
        const { wrapper } = await createWrapper({ deliveries: [] });

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-data-grid').exists()).toBe(false);
    });

    it('shows an error instead of loading forever when the deliveries cannot be loaded', async () => {
        const { wrapper } = await createWrapper({
            search: jest.fn(() => Promise.reject(new Error('Bad Request'))),
        });

        expect(wrapper.find('.sw-settings-webhook-deliveries-modal__load-error').exists()).toBe(true);
        expect(wrapper.find('.sw-data-grid').exists()).toBe(false);
    });

    it('emits modal-close when the modal is closed', async () => {
        const { wrapper } = await createWrapper();

        child(wrapper, 'mt-modal-root').vm.$emit('change', false);

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
