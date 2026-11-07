/**
 * @sw-package checkout
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

const selectedIds = [
    'order-1',
    'order-2',
];

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function deferred() {
    let resolve = () => {};
    const promise = new Promise((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

async function createRoutes() {
    const step = async (name, path, component) => ({
        name: `sw.bulk.edit.order.save.${name}`,
        path,
        component: await wrapTestComponent(component, { sync: true }),
        meta: { $module: { title: 'sw-bulk-edit-order.general.mainMenuTitle' } },
    });

    return [
        {
            name: 'sw.bulk.edit.order',
            path: '/index/:parentId?/:excludeDelivery?',
            component: { template: '<div></div>' },
        },
        {
            name: 'sw.bulk.edit.order.save',
            path: '',
            component: await wrapTestComponent('sw-bulk-edit-save-modal', { sync: true }),
            meta: { $module: { title: 'sw-bulk-edit-order.general.mainMenuTitle' } },
            redirect: { name: 'sw.bulk.edit.order.save.confirm' },
            children: [
                await step('confirm', '/confirm', 'sw-bulk-edit-save-modal-confirm'),
                await step('process', '/process', 'sw-bulk-edit-save-modal-process'),
                await step('success', '/success', 'sw-bulk-edit-save-modal-success'),
                await step('error', 'error', 'sw-bulk-edit-save-modal-error'),
            ],
        },
    ];
}

async function createWrapper(handler) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({ history: createWebHashHistory(), routes: await createRoutes() });
    router.push('/');
    await router.isReady();

    Shopware.Store.get('swBulkEdit').selectedIds = [...selectedIds];

    return mount(await wrapTestComponent('sw-bulk-edit-order', { sync: true }), {
        global: {
            plugins: [router],
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-card-view': true,
                'mt-empty-state': true,
                'sw-search-bar': true,
                'sw-label': true,
            },
            provide: {
                bulkEditApiFactory: {
                    getHandler: () => handler,
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'state_machine_state') {
                            return { searchIds: () => Promise.resolve({ data: [], total: 0 }) };
                        }

                        return {
                            create: () => ({ id: 'order-id' }),
                            search: () => Promise.resolve(createEntityCollection()),
                            searchIds: () => Promise.resolve({ data: [], total: 0 }),
                            get: () => Promise.resolve({ id: '' }),
                        };
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve(createEntityCollection()),
                },
                orderDocumentApiService: {
                    create: () => Promise.resolve(),
                    download: () => Promise.resolve(),
                    extendingDeprecatedService: () => Promise.resolve({ data: { showWarning: false } }),
                },
                documentV2Service: {},
                documentV2ApiService: {
                    getAvailableTypes: () => Promise.resolve({ documentTypes: {} }),
                },
                validationService: {},
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
                syncService: {
                    sync: () => Promise.resolve(),
                },
            },
        },
        props: {
            title: 'Bulk edit',
        },
    });
}

async function confirmSave(wrapper) {
    await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');
    await flushPromises();

    await wrapper.find('.footer-right button').trigger('click');
    await flushPromises();
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order status processing', () => {
    beforeEach(() => {
        Shopware.Store.get('swBulkEdit').$reset();
    });

    it('lists every failed status change in the error step', async () => {
        const failures = [
            {
                orderId: 'order-1',
                orderNumber: '10001',
                field: 'orderTransactions',
                reason: 'transition',
                code: 'CHECKOUT__ORDER_TRANSITION_NOT_POSSIBLE',
            },
        ];
        const handler = {
            bulkEditStatus: jest.fn(() => Promise.reject(Object.assign(new Error('failed'), { failures }))),
            bulkEdit: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper(handler);
        await flushPromises();

        await wrapper.setData({ bulkEditData: { orderTransactions: { isChanged: true, value: 'paid' } } });
        await confirmSave(wrapper);

        expect(wrapper.find('.sw-bulk-edit-save-modal-error').exists()).toBe(true);

        const items = wrapper.findAll('.sw-bulk-edit-save-modal-error__failed-status-list li');
        expect(items).toHaveLength(1);
        expect(items[0].text()).toContain('10001');
        expect(items[0].text()).toContain('sw-bulk-edit.order.status.failedFields.orderTransactions');
    });

    it('sends the status changes once for all orders before the other edits', async () => {
        const status = deferred();
        const handler = {
            bulkEditStatus: jest.fn(() => status.promise),
            bulkEdit: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper(handler);
        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                orderTransactions: { isChanged: true, value: 'paid' },
                customFields: { value: { customFieldName: 'value' } },
            },
        });
        await confirmSave(wrapper);

        expect(handler.bulkEditStatus).toHaveBeenCalledTimes(1);
        expect(handler.bulkEditStatus).toHaveBeenCalledWith(selectedIds, [
            expect.objectContaining({ field: 'orderTransactions' }),
        ]);
        expect(handler.bulkEdit).not.toHaveBeenCalled();

        status.resolve([]);
        await flushPromises();

        expect(handler.bulkEdit).toHaveBeenCalledWith(selectedIds, [
            expect.objectContaining({ field: 'customFields' }),
        ]);
        expect(wrapper.find('.sw-bulk-edit-save-modal-success').exists()).toBe(true);
    });
});
