import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

async function createWrapper(order = {}) {
    return mount(await wrapTestComponent('sw-order-detail', { sync: true }), {
        global: {
            mocks: {
                $route: {
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
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-label': true,
                'sw-skeleton': true,
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-card-view': {
                    template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>`,
                },
                'sw-alert': true,
                'sw-loader': true,
                'router-view': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-icon': true,
                'sw-language-switch': true,
                'sw-order-leave-page-modal': true,
                'sw-extension-component-section': true,
                'router-link': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        hasChanges: () => false,
                        deleteVersion: () => Promise.resolve([]),
                        createVersion: () => Promise.resolve({ versionId: 'newVersionId' }),
                        get: () => Promise.resolve(order),
                        save: () => Promise.resolve({}),
                    }),
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

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should remove version id when beforeunload event is triggered', async () => {
        wrapper = await createWrapper();
        wrapper.vm.orderRepository.deleteVersion = jest.fn(() => Promise.resolve());

        const oldVersionContext = wrapper.vm.versionContext;

        window.dispatchEvent(new Event('beforeunload'));

        expect(wrapper.vm.orderRepository.deleteVersion).toHaveBeenCalledWith(
            wrapper.vm.orderId,
            oldVersionContext.versionId,
            oldVersionContext,
        );
        expect(wrapper.vm.versionContext).toBe(Shopware.Context.api);
        expect(wrapper.vm.hasNewVersionId).toBe(false);
    });

    it('should not contain manual label', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeFalsy();
    });

    it('should contain manual label', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ identifier: '1', createdById: '2' });

        await Shopware.State.commit('swOrderDetail/setOrder', {
            orderNumber: 1,
        });

        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeTruthy();
    });

    it('should created a new version when component was created', async () => {
        wrapper = await createWrapper();
        const createNewVersionIdSpy = jest.spyOn(wrapper.vm, 'createNewVersionId');

        await wrapper.vm.createdComponent();

        expect(createNewVersionIdSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.hasNewVersionId).toBeTruthy();
    });

    it('should clean up unsaved version when component gets destroyed', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.createNewVersionId();
        wrapper.vm.orderRepository.deleteVersion = jest.fn(() => Promise.resolve());

        await wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.orderRepository.deleteVersion).toHaveBeenCalled();
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

    it('should apply promotions on save and recalculate', async () => {
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
        expect(wrapper.vm.orderService.toggleAutomaticPromotions).toHaveBeenCalled();
    });

    it('should apply promotions on recalculate and reload', async () => {
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

        expect(wrapper.vm.promotionsToDelete).toHaveLength(1);
        expect(wrapper.vm.deliveryDiscountsToDelete).toHaveLength(1);
        expect(wrapper.vm.orderService.recalculateOrder).toHaveBeenCalled();
        expect(wrapper.vm.orderService.toggleAutomaticPromotions).toHaveBeenCalled();
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
});
