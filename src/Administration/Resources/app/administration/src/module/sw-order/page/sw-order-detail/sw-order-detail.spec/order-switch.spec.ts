/**
 * @sw-package checkout
 */
import { config, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';

function createOrderRepository() {
    return {
        createVersion: jest.fn((orderId: string) => Promise.resolve({ versionId: `${orderId}-draft` })),
        deleteVersion: jest.fn(() => Promise.resolve()),
        deleteVersionWithKeepalive: jest.fn(() => Promise.resolve()),
        get: jest.fn((orderId: string, versionContext: { versionId: string }) =>
            Promise.resolve({ id: orderId, versionId: versionContext.versionId, lineItems: [] }),
        ),
        hasChanges: () => false,
    };
}

async function createWrapper(orderRepository: ReturnType<typeof createOrderRepository>) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const orderTab = {
        emits: ['reload-entity-data'],
        template: `<button @click="$emit('reload-entity-data')">Edit order</button>`,
    };

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                name: 'sw.order.index',
                path: '/sw/order/index',
                component: { template: '<div></div>' },
            },
            {
                name: 'sw.order.detail',
                path: '/sw/order/detail/:id',
                component: await wrapTestComponent('sw-order-detail', { sync: true }),
                props: (route) => ({ orderId: route.params.id }),
                meta: { $module: { routes: { detail: { children: [] } } } },
                children: [
                    { name: 'sw.order.detail.general', path: 'general', component: orderTab },
                    { name: 'sw.order.detail.details', path: 'details', component: orderTab },
                ],
            },
        ],
    });

    await router.push('/sw/order/detail/order-a/general');

    const wrapper = mount(
        { template: '<router-view />' },
        {
            global: {
                plugins: [router],
                stubs: {
                    'sw-page': {
                        template: '<div class="sw-page"><slot name="content"></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-order-leave-page-modal': await wrapTestComponent('sw-order-leave-page-modal', { sync: true }),
                    'sw-order-save-changes-beforehand-modal': true,
                    'sw-extension-component-section': true,
                    'sw-skeleton': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => orderRepository,
                    },
                    orderService: {},
                },
            },
        },
    );
    await flushPromises();

    return { wrapper, router };
}

async function clickButton(wrapper: VueWrapper, label: string): Promise<void> {
    const button = wrapper.findAll('button').find((element) => element.text() === label);

    if (!button) {
        throw new Error(`No button labelled "${label}"`);
    }

    await button.trigger('click');
    await flushPromises();
}

describe('src/module/sw-order/page/sw-order-detail order switch', () => {
    beforeEach(() => {
        Shopware.Store.get('swOrderDetail').$reset();
    });

    it('discards the draft of the previous order when switching to another order', async () => {
        const orderRepository = createOrderRepository();
        const { router } = await createWrapper(orderRepository);

        await router.push('/sw/order/detail/order-b/general');
        await flushPromises();

        expect(orderRepository.deleteVersion).toHaveBeenCalledTimes(1);
        expect(orderRepository.deleteVersion).toHaveBeenCalledWith('order-a', 'order-a-draft');
        expect(orderRepository.createVersion).toHaveBeenLastCalledWith('order-b');
    });

    it('deletes a draft that arrives after switching to another order instead of using it', async () => {
        const orderRepository = createOrderRepository();
        let resolveFirstDraft: (context: { versionId: string }) => void = () => {};
        orderRepository.createVersion.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveFirstDraft = resolve;
                }),
        );
        const { router } = await createWrapper(orderRepository);

        await router.push('/sw/order/detail/order-b/general');
        await flushPromises();

        resolveFirstDraft({ versionId: 'order-a-draft' });
        await flushPromises();

        expect(orderRepository.deleteVersion).toHaveBeenCalledTimes(1);
        expect(orderRepository.deleteVersion).toHaveBeenCalledWith('order-a', 'order-a-draft');
        expect(orderRepository.get).not.toHaveBeenCalledWith('order-a', expect.anything(), expect.anything());
        expect(Shopware.Store.get('swOrderDetail').versionContext?.versionId).toBe('order-b-draft');
    });

    it('deletes a draft that arrives after leaving the order page', async () => {
        const orderRepository = createOrderRepository();
        let resolveDraft: (context: { versionId: string }) => void = () => {};
        orderRepository.createVersion.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveDraft = resolve;
                }),
        );
        const { router } = await createWrapper(orderRepository);

        await router.push('/sw/order/index');
        await flushPromises();

        resolveDraft({ versionId: 'order-a-draft' });
        await flushPromises();

        expect(orderRepository.deleteVersion).toHaveBeenCalledWith('order-a', 'order-a-draft');
        expect(orderRepository.get).not.toHaveBeenCalled();
    });

    it('switches the tabs of an order with unsaved changes without asking', async () => {
        const orderRepository = createOrderRepository();
        const { wrapper, router } = await createWrapper(orderRepository);
        await clickButton(wrapper, 'Edit order');

        void router.push('/sw/order/detail/order-a/details');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('sw.order.detail.details');
        expect(wrapper.text()).not.toContain('sw-order.leavePageModal.description');
        expect(orderRepository.deleteVersion).not.toHaveBeenCalled();
    });

    it('stays on the order with unsaved changes when switching to another order is cancelled', async () => {
        const orderRepository = createOrderRepository();
        const { wrapper, router } = await createWrapper(orderRepository);
        await clickButton(wrapper, 'Edit order');

        void router.push('/sw/order/detail/order-b/general');
        await flushPromises();

        expect(wrapper.text()).toContain('sw-order.leavePageModal.description');
        expect(router.currentRoute.value.params.id).toBe('order-a');

        await clickButton(wrapper, 'sw-order.leavePageModal.cancelButton');

        expect(router.currentRoute.value.params.id).toBe('order-a');
        expect(wrapper.text()).not.toContain('sw-order.leavePageModal.description');
        expect(wrapper.text()).toContain('sw-order.detail.textUnsavedOrderWarning');
        expect(orderRepository.deleteVersion).not.toHaveBeenCalled();
    });

    it('discards the unsaved changes when leaving the order for another one', async () => {
        const orderRepository = createOrderRepository();
        const { wrapper, router } = await createWrapper(orderRepository);
        await clickButton(wrapper, 'Edit order');

        void router.push('/sw/order/detail/order-b/general');
        await flushPromises();

        await clickButton(wrapper, 'sw-order.leavePageModal.confirmButton');

        expect(router.currentRoute.value.params.id).toBe('order-b');
        expect(orderRepository.deleteVersion).toHaveBeenCalledWith('order-a', 'order-a-draft');
        expect(wrapper.text()).not.toContain('sw-order.detail.textUnsavedOrderWarning');
    });
});
