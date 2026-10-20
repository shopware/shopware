/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

type Delivery = {
    id: string;
    deliveryStatus: string;
    processingTime?: number;
    requestContent?: { body?: string };
    responseContent?: { body?: string };
    responseReasonPhrase?: string;
};

const delivered: Delivery = {
    id: 'delivered',
    deliveryStatus: 'success',
    processingTime: 42,
    requestContent: { body: '{"event":"order.placed","data":{"payload":[{"id":"order-1","orderNumber":"10000"}]}}' },
    responseContent: { body: 'OK' },
};

const failed: Delivery = {
    id: 'failed',
    deliveryStatus: 'failed',
    requestContent: { body: 'not json' },
    responseContent: { body: '' },
    responseReasonPhrase: 'Service Unavailable',
};

function createCollection<T>(items: T[]) {
    return Object.assign([...items], { total: items.length });
}

async function createWrapper({
    webhook = { name: 'Order placed' } as { name: string; app?: { name: string } },
    deliveries = [
        delivered,
        failed,
    ] as Delivery[],
} = {}) {
    const search = jest.fn(() => Promise.resolve(createCollection(deliveries)));

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
                    props: ['title'],
                    template: `
                        <div class="mt-modal">
                            <h2 class="mt-modal__title">{{ title }}</h2>
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
                'sw-time-ago': true,
                'mt-empty-state': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, search };
}

describe('src/module/sw-settings-webhook/component/sw-settings-webhook-deliveries-modal', () => {
    it('loads the latest deliveries of the webhook by its name', async () => {
        const { wrapper, search } = await createWrapper();

        expect(wrapper.find('.mt-modal__title').text()).toBe('sw-settings-webhook.deliveries.title');

        expect(search).toHaveBeenCalledTimes(1);
        const criteria = (search.mock.calls[0] as unknown as [CriteriaType])[0];
        expect(criteria.limit).toBe(25);
        expect(criteria.filters).toEqual([expect.objectContaining({ field: 'webhookName', value: 'Order placed' })]);
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'createdAt', order: 'DESC' })]);
    });

    it('also matches the app of an app webhook', async () => {
        const { search } = await createWrapper({ webhook: { name: 'Order placed', app: { name: 'MyApp' } } });

        const criteria = (search.mock.calls[0] as unknown as [CriteriaType])[0];
        expect(criteria.filters).toEqual([
            expect.objectContaining({ field: 'webhookName', value: 'Order placed' }),
            expect.objectContaining({ field: 'appName', value: 'MyApp' }),
        ]);
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

    it('shows a short preview of the payload and the response', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-delivered .payload').text()).toBe('{"event":"order.placed","data"…');
        expect(wrapper.find('.row-delivered .response').text()).toBe('OK');
        expect(wrapper.find('.row-delivered .duration').text()).toBe('42 ms');
    });

    it('shows the reason phrase when the response has no body', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.row-failed .response a').exists()).toBe(false);
        expect(wrapper.find('.row-failed .response').text()).toBe('Service Unavailable');
        expect(wrapper.find('.row-failed .duration').text()).toBe('');
    });

    it('opens the formatted payload of a delivery and goes back to the list', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.row-delivered .payload a').trigger('click');

        expect(wrapper.find('.sw-data-grid').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('payload');
        expect(wrapper.find('pre').text()).toBe(
            JSON.stringify({ event: 'order.placed', data: { payload: [{ id: 'order-1', orderNumber: '10000' }] } }, null, 2),
        );

        (wrapper.findComponent({ name: 'mt-tabs' }).vm as ComponentPublicInstance).$emit('new-item-active', 'response');
        await flushPromises();

        expect(wrapper.find('pre').text()).toBe('OK');

        await wrapper.find('.mt-modal__footer button').trigger('click');

        expect(wrapper.find('pre').exists()).toBe(false);
        expect(wrapper.find('.sw-data-grid').exists()).toBe(true);
    });

    it('opens the response of a delivery and shows a body that is no JSON as it is', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.row-delivered .response a').trigger('click');

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('response');
        expect(wrapper.find('pre').text()).toBe('OK');

        await wrapper.find('.mt-modal__footer button').trigger('click');
        await wrapper.find('.row-failed .payload a').trigger('keydown.enter');

        expect(wrapper.find('pre').text()).toBe('not json');
    });

    it('shows the empty state when the webhook has no deliveries', async () => {
        const { wrapper } = await createWrapper({ deliveries: [] });

        expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-data-grid').exists()).toBe(false);
    });

    it('emits modal-close when the modal is closed', async () => {
        const { wrapper } = await createWrapper();

        (wrapper.findComponent({ name: 'mt-modal-root' }).vm as ComponentPublicInstance).$emit('change', false);

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
