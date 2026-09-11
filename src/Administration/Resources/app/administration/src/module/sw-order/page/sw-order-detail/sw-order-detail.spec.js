/* eslint-disable sw-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

/**
 * @sw-package checkout
 */

async function createWrapper(order = {}, { routeName = 'sw.order.detail.general', routerPush = jest.fn() } = {}) {
    const repositoryFactoryMock = {
        search: () => Promise.resolve([]),
        hasChanges: () => false,
        deleteVersion: () => Promise.resolve([]),
        deleteVersionWithKeepalive: () => Promise.resolve(),
        createVersion: () => Promise.resolve({ versionId: 'newVersionId' }),
        get: () => Promise.resolve(order),
        save: () => Promise.resolve({}),
    };

    return mount(await wrapTestComponent('sw-order-detail', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    name: routeName,
                    params: {
                        id: 'order123',
                    },
                    meta: {
                        $module: {
                            routes: {
                                detail: {
                                    children: [
                                        {
                                            name: 'sw.order.detail.general',
                                        },
                                        {
                                            name: 'sw.order.detail.details',
                                        },
                                        {
                                            name: 'sw.order.detail.document',
                                        },
                                    ],
                                },
                            },
                        },
                    },
                },
                $router: {
                    push: routerPush,
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-label': true,
                'sw-skeleton': true,
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-card-view': {
                    template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>`,
                },

                'sw-loader': true,
                'router-view': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot></slot></div>',
                    props: [
                        'positionIdentifier',
                    ],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<div class="sw-tabs-item"></div>',
                    props: [
                        'route',
                        'title',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
                'sw-language-switch': true,
                'sw-order-leave-page-modal': true,
                'sw-order-save-changes-beforehand-modal': true,
                'sw-extension-component-section': true,
                'router-link': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => repositoryFactoryMock,
                },
                orderService: {},
            },
        },
        props: {
            orderId: Shopware.Utils.createId(),
        },
    });
}

describe('src/module/sw-order/page/sw-order-detail', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            window.removeEventListener('pagehide', wrapper.vm.onPageHide);
        }

        Shopware.Store.get('shopwareApps').selectedIds = [];
    });

    it('should select the displayed order for app action buttons', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([
            wrapper.vm.orderId,
        ]);
    });

    it('should deselect the order for app action buttons when leaving the detail page while editing', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ hasOrderDeepEdit: true });

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, {}, {}, next);

        // The leave page warning takes over, so the navigation is not continued yet
        expect(next).not.toHaveBeenCalled();
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(true);
        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([
            wrapper.vm.orderId,
        ]);

        wrapper.unmount();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([]);
    });

    it('should remove version id with a keepalive request when pagehide is triggered', async () => {
        wrapper = await createWrapper();
        wrapper.vm.orderRepository.deleteVersion = jest.fn(() => Promise.resolve());
        wrapper.vm.orderRepository.deleteVersionWithKeepalive = jest.fn(() => Promise.resolve());

        const oldVersionContext = wrapper.vm.versionContext;

        window.dispatchEvent(new Event('pagehide'));

        expect(wrapper.vm.orderRepository.deleteVersionWithKeepalive).toHaveBeenCalledWith(
            wrapper.vm.orderId,
            oldVersionContext.versionId,
        );
        expect(wrapper.vm.orderRepository.deleteVersion).not.toHaveBeenCalled();
        expect(wrapper.vm.versionContext).toBe(Shopware.Context.api);
        expect(wrapper.vm.hasNewVersionId).toBe(false);
    });

    it('should keep the version when the page is stored in the back-forward cache', async () => {
        wrapper = await createWrapper();
        wrapper.vm.orderRepository.deleteVersionWithKeepalive = jest.fn(() => Promise.resolve());

        const pageHideEvent = new Event('pagehide');
        Object.defineProperty(pageHideEvent, 'persisted', { value: true });
        window.dispatchEvent(pageHideEvent);

        expect(wrapper.vm.orderRepository.deleteVersionWithKeepalive).not.toHaveBeenCalled();
        expect(wrapper.vm.hasNewVersionId).toBe(true);
    });

    it('should not contain manual label', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeFalsy();
    });

    it('should contain manual label', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ identifier: '1' });

        Shopware.Store.get('swOrderDetail').order = {
            orderNumber: 1,
            createdById: '2',
        };
        await nextTick();

        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeTruthy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render the fallback tabs branch', async () => {
        wrapper = await createWrapper();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-detail');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        wrapper = await createWrapper(
            {},
            {
                routeName: 'sw.order.detail.details',
            },
        );

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-detail');
        expect(tabs.props('defaultItem')).toBe('sw.order.detail.details');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-order.detail.tabGeneral',
                name: 'sw.order.detail.general',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.detail.tabDetails',
                name: 'sw.order.detail.details',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-order.detail.tabDocuments',
                name: 'sw.order.detail.documents',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should navigate when a meteor route tab is clicked', async () => {
        const routerPush = jest.fn();
        wrapper = await createWrapper(
            {},
            {
                routerPush,
            },
        );

        const documentsTab = wrapper.vm.orderDetailTabs.find((tab) => tab.name === 'sw.order.detail.documents');

        documentsTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.order.detail.documents',
            params: { id: 'order123' },
        });
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should pass the document warning state to meteor tabs', async () => {
        wrapper = await createWrapper();

        wrapper.vm.hasOrderDeepEdit = true;
        await nextTick();

        const documentsTab = wrapper
            .getComponent({ name: 'mt-tabs' })
            .props('items')
            .find((tab) => tab.name === 'sw.order.detail.documents');

        expect(documentsTab).toEqual(
            expect.objectContaining({
                badge: 'warning',
            }),
        );
    });

    it('should created a new version when component was created', async () => {
        wrapper = await createWrapper();
        const createNewVersionIdSpy = jest.spyOn(wrapper.vm, 'createNewVersionId');

        await wrapper.vm.createdComponent();

        expect(createNewVersionIdSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.hasNewVersionId).toBeTruthy();
    });

    it('should reset pending address selections when creating a new version', async () => {
        wrapper = await createWrapper();

        Shopware.Store.get('swOrderDetail').setOrderAddressIds({
            orderAddressId: 'old-order-address-id',
            customerAddressId: 'customer-address-id',
            type: 'billing',
        });

        await wrapper.vm.createNewVersionId();

        expect(Shopware.Store.get('swOrderDetail').orderAddressIds).toEqual([]);
    });

    it('should clean up unsaved version when component gets destroyed', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.createNewVersionId();
        wrapper.vm.orderRepository.deleteVersion = jest.fn(() => Promise.resolve());
        const oldVersionId = wrapper.vm.versionContext.versionId;

        wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.orderRepository.deleteVersion).toHaveBeenCalledWith(wrapper.vm.orderId, oldVersionId);
    });

    it('should reset pending address selections when component gets destroyed', async () => {
        wrapper = await createWrapper();

        Shopware.Store.get('swOrderDetail').setOrderAddressIds({
            orderAddressId: 'old-order-address-id',
            customerAddressId: 'customer-address-id',
            type: 'billing',
        });

        wrapper.vm.beforeDestroyComponent();

        expect(Shopware.Store.get('swOrderDetail').orderAddressIds).toEqual([]);
    });

    it('should remove version context immediately when cancelling', async () => {
        wrapper = await createWrapper();
        const oldVersionId = wrapper.vm.versionContext.versionId;
        wrapper.vm.orderRepository.deleteVersion = jest.fn(() => {
            expect(wrapper.vm.versionContext.versionId).not.toBe(oldVersionId);

            return Promise.resolve();
        });

        await wrapper.vm.onCancelEditing();

        expect(wrapper.vm.orderRepository.deleteVersion).toHaveBeenCalled();
    });

    it('should reload entity data with orderCriteria', async () => {
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.getLimit()).toBe(25);
        [
            'currency',
            'orderCustomer',
            'language',
            'lineItems',
            'salesChannel',
            'addresses',
            'deliveries',
            'transactions',
            'documents',
            'tags',
            'billingAddress',
        ].forEach((association) => expect(criteria.hasAssociation(association)).toBe(true));
        expect(criteria.getAssociation('orderCustomer').hasAssociation('customer')).toBe(true);
    });

    it('should add associations no longer autoload in the orderCriteria', async () => {
        wrapper = await createWrapper();
        const criteria = wrapper.vm.orderCriteria;

        expect(criteria.hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('deliveries').hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('transactions').hasAssociation('stateMachineState')).toBe(true);
    });

    it('should convert product line items that are missing', async () => {
        const lineItemWithExistingProduct = {
            id: 'lineItemId',
            type: 'product',
            referencedId: 'productId',
            quantity: 1,
            productId: 'productId',
            payload: {},
        };
        const lineItemWithMissingProduct = {
            ...lineItemWithExistingProduct,
            productId: null,
        };
        const previouslyConvertedLineItem = {
            ...lineItemWithMissingProduct,
            referencedId: null,
            type: 'custom',
            payload: { isConvertedProductLineItem: true },
        };

        wrapper = await createWrapper({
            lineItems: [
                lineItemWithMissingProduct,
                lineItemWithExistingProduct,
                previouslyConvertedLineItem,
            ],
        });
        await flushPromises();

        const missingProductLineItems = wrapper.vm.missingProductLineItems;
        expect(missingProductLineItems).toHaveLength(1);
        expect(missingProductLineItems).toContainEqual(lineItemWithMissingProduct);
        expect(lineItemWithMissingProduct.referencedId).toBeNull();
        expect(lineItemWithMissingProduct.type).toBe('custom');

        const convertedProductLineItems = wrapper.vm.convertedProductLineItems;
        expect(convertedProductLineItems).toHaveLength(1);
        expect(convertedProductLineItems).toContainEqual(previouslyConvertedLineItem);
    });

    it('should not apply promotions on save and recalculate', async () => {
        const lineItemWithExistingProduct = {
            id: 'lineItemId',
            type: 'product',
            referencedId: 'productId',
            quantity: 1,
            productId: 'productId',
            payload: {},
        };

        const promotionLineItem = {
            type: 'promotion',
            referencedId: null,
        };

        const deliveryDiscount = {
            id: 'deliveryId2',
        };

        const deliveries = [
            {
                id: 'deliveryId',
            },
            deliveryDiscount,
        ];

        wrapper = await createWrapper({
            primaryOrderDeliveryId: 'deliveryId',
            lineItems: [
                lineItemWithExistingProduct,
                promotionLineItem,
            ],
            deliveries,
        });

        wrapper.vm.orderService.recalculateOrder = jest.fn(() => Promise.resolve());
        wrapper.vm.orderService.toggleAutomaticPromotions = jest.fn(() => Promise.resolve());

        await flushPromises();

        expect(wrapper.vm.automaticPromotions).toHaveLength(1);
        expect(wrapper.vm.deliveryDiscounts).toHaveLength(1);
        expect(wrapper.vm.automaticPromotions).toContainEqual(promotionLineItem);
        expect(wrapper.vm.deliveryDiscounts).toContainEqual(deliveryDiscount);

        await wrapper.vm.onSaveAndRecalculate();
        expect(wrapper.vm.orderService.recalculateOrder).toHaveBeenCalled();
        expect(wrapper.vm.orderService.toggleAutomaticPromotions).not.toHaveBeenCalled();
    });

    it('should not apply promotions on recalculate and reload', async () => {
        const lineItemWithExistingProduct = {
            id: 'lineItemId',
            type: 'product',
            referencedId: 'productId',
            quantity: 1,
            productId: 'productId',
            payload: {},
        };

        const promotionLineItem = {
            type: 'promotion',
            referencedId: null,
        };

        const deliveryDiscount = {
            id: 'deliveryId2',
        };

        const deliveries = [
            {
                id: 'deliveryId',
            },
            deliveryDiscount,
        ];

        wrapper = await createWrapper({
            primaryOrderDeliveryId: 'deliveryId',
            lineItems: [
                lineItemWithExistingProduct,
                promotionLineItem,
            ],
            deliveries,
        });

        wrapper.vm.orderService.recalculateOrder = jest.fn(() => Promise.resolve());
        wrapper.vm.orderService.toggleAutomaticPromotions = jest.fn(() => Promise.resolve());

        await flushPromises();

        expect(wrapper.vm.automaticPromotions).toHaveLength(1);
        expect(wrapper.vm.deliveryDiscounts).toHaveLength(1);
        expect(wrapper.vm.automaticPromotions).toContainEqual(promotionLineItem);
        expect(wrapper.vm.deliveryDiscounts).toContainEqual(deliveryDiscount);

        await wrapper.vm.onRecalculateAndReload();

        expect(wrapper.vm.promotionsToDelete).toHaveLength(0);
        expect(wrapper.vm.deliveryDiscountsToDelete).toHaveLength(0);
        expect(wrapper.vm.orderService.recalculateOrder).toHaveBeenCalled();
        expect(wrapper.vm.orderService.toggleAutomaticPromotions).not.toHaveBeenCalled();
    });

    it('should delete promotions on save edits', async () => {
        const lineItemWithExistingProduct = {
            id: 'lineItemId',
            type: 'product',
            referencedId: 'productId',
            quantity: 1,
            productId: 'productId',
            payload: {},
        };

        const promotionLineItem = {
            id: 'promotionLineItemId',
            type: 'promotion',
            referencedId: null,
        };

        const deliveryDiscount = {
            id: 'deliveryId2',
        };

        const deliveries = [
            {
                id: 'deliveryId',
            },
            deliveryDiscount,
        ];

        wrapper = await createWrapper({
            lineItems: [
                lineItemWithExistingProduct,
                promotionLineItem,
            ],
            deliveries,
        });

        await flushPromises();

        wrapper.vm.promotionsToDelete = ['promotionLineItemId'];
        wrapper.vm.deliveryDiscountsToDelete = ['deliveryId2'];

        await wrapper.vm.onSaveEdits();

        expect(wrapper.vm.order.lineItems).toHaveLength(1);
        expect(wrapper.vm.order.deliveries).toHaveLength(1);
        expect(wrapper.vm.promotionsToDelete).toHaveLength(0);
        expect(wrapper.vm.deliveryDiscountsToDelete).toHaveLength(0);
    });

    it('should handle order address update', async () => {
        wrapper = await createWrapper({
            id: 'order123',
            primaryOrderDeliveryId: 'delivery123',
            primaryOrderDelivery: {
                id: 'delivery123',
            },
            deliveries: [
                {
                    id: 'delivery123',
                },
            ],
        });

        wrapper.vm.orderService.updateOrderAddresses = jest.fn(() => Promise.resolve());

        await flushPromises();

        const addressMappings = [
            {
                orderAddressId: 'orderAddress1',
                customerAddressId: 'customerAddress1',
                type: 'billing',
            },
            {
                orderAddressId: 'orderAddress2',
                customerAddressId: 'customerAddress2',
                type: 'shipping',
            },
        ];

        await wrapper.vm.handleOrderAddressUpdate(addressMappings);

        expect(wrapper.vm.orderService.updateOrderAddresses).toHaveBeenCalledWith(
            wrapper.vm.orderId,
            [
                {
                    customerAddressId: 'customerAddress1',
                    type: 'billing',
                },
                {
                    customerAddressId: 'customerAddress2',
                    type: 'shipping',
                    deliveryId: 'delivery123',
                },
            ],
            {},
            { 'sw-version-id': undefined },
        );
    });

    it('should skip order address update', async () => {
        wrapper = await createWrapper();

        const addressMappings = [
            {
                orderAddressId: 'address',
                customerAddressId: 'address',
                type: 'billing',
            },
        ];

        wrapper.vm.orderService.updateOrderAddress = jest.fn(() => Promise.resolve());

        await wrapper.vm.handleOrderAddressUpdate(addressMappings);

        expect(wrapper.vm.orderService.updateOrderAddress).not.toHaveBeenCalled();
    });

    it('should notification error when order line items are empty', async () => {
        const createNotificationErrorMock = jest.fn();
        const createNewVersionIdMock = jest.fn().mockResolvedValue();

        wrapper = await createWrapper({
            lineItems: [],
        });

        wrapper.vm.createNotificationError = createNotificationErrorMock;
        wrapper.vm.createNewVersionId = createNewVersionIdMock;

        await wrapper.vm.onSaveEdits({
            lineItems: [],
        });

        expect(createNotificationErrorMock).toHaveBeenCalledWith({
            message: 'sw-order.detail.messageEmptyLineItems',
        });

        expect(createNewVersionIdMock).toHaveBeenCalled();
        expect(Shopware.Store.get('swOrderDetail').isLoading).toBe(false);
    });

    it('should prefer the server translated message when handling cart errors', async () => {
        wrapper = await createWrapper();

        const createNotificationErrorMock = jest.fn();
        wrapper.vm.createNotificationError = createNotificationErrorMock;

        wrapper.vm.handleCartErrors({
            data: {
                errors: {
                    'promotion-not-found': {
                        level: 20,
                        message: 'Promotion with code SUMMER not found!',
                        messageKey: 'promotion-not-found',
                        translatedMessage: 'Gutscheincode "SUMMER" existiert nicht.',
                    },
                    'custom-plugin-error': {
                        level: 20,
                        message: 'Something went wrong',
                        messageKey: 'custom-plugin-error',
                        translatedMessage: 'checkout.custom-plugin-error',
                    },
                },
            },
        });

        expect(createNotificationErrorMock).toHaveBeenNthCalledWith(1, {
            message: 'Gutscheincode "SUMMER" existiert nicht.',
        });

        expect(createNotificationErrorMock).toHaveBeenNthCalledWith(2, {
            message: 'Something went wrong',
        });
    });

    it('should ask for saving confirmation before continuing', async () => {
        wrapper = await createWrapper();
        const onSaveEditsSpy = jest.fn();
        wrapper.vm.onSaveEdits = onSaveEditsSpy;

        let promise = wrapper.vm.askAndSaveEdits();

        expect(wrapper.vm.askForSaveBeforehand).toBeFalsy();
        expect(await promise).toBe(true);
        expect(onSaveEditsSpy).not.toHaveBeenCalled();

        wrapper.vm.hasOrderDeepEdit = true;
        await flushPromises();

        promise = wrapper.vm.askAndSaveEdits();
        expect(wrapper.vm.askForSaveBeforehand).toBeTruthy();

        wrapper.vm.onAskAndSaveEditsCancel();
        expect(await promise).toBe(false);
        expect(onSaveEditsSpy).not.toHaveBeenCalled();

        promise = wrapper.vm.askAndSaveEdits();
        expect(wrapper.vm.askForSaveBeforehand).toBeTruthy();

        wrapper.vm.onAskAndSaveEditsConfirm();
        Shopware.Store.get('swOrderDetail').savedSuccessful = true;
        expect(await promise).toBe(true);
        expect(onSaveEditsSpy).toHaveBeenCalled();
    });

    it('should call afterSaveFn of saveAndReload', async () => {
        wrapper = await createWrapper();

        let promiseResolved = false;
        const afterSaveFn = jest.fn(() =>
            new Promise((r) => {
                r();
            }).then(() => {
                promiseResolved = true;
            }),
        );

        await wrapper.vm.saveAndReload(afterSaveFn);

        expect(afterSaveFn).toHaveBeenCalledTimes(1);
        expect(promiseResolved).toBe(true);
    });
});
