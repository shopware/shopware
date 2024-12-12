import type { AxiosResponse } from 'axios';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import type { Cart, ContextSwitchParameters, LineItem, SalesChannelContext } from '../order.types';
import type CartStoreService from '../../../core/service/api/cart-store-api.api.service';
import type ContextStoreService from '../../../core/service/api/store-context.api.service';
import type CheckoutStoreService from '../../../core/service/api/checkout-store.api.service';
import type OrderStateMachineApiService from '../../../core/service/api/order-state-machine.api.service';

describe('src/module/sw-order/store/order.store', () => {
    const store = Shopware.Store.get('swOrder');

    const token = 'GH36L55342HJKBTD78H93K2JLH93K2JL';
    const cart = {
        token,
        lineItems: [],
        price: {
            totalPrice: 100,
        },
        deliveries: [],
    } as unknown as Cart;

    const item = {
        id: '1',
        name: 'test',
    } as unknown as LineItem;

    const createCartMock = jest.fn(() => Promise.resolve({ data: { token, lineItems: [] } } as unknown as AxiosResponse));
    const getCartMock = jest.fn(() => Promise.resolve({ data: { token, lineItems: [] } } as unknown as AxiosResponse));
    const cancelCartMock = jest.fn(() => Promise.resolve({ data: {} } as unknown as AxiosResponse));
    const addPromotionCodeMock = jest.fn(() => Promise.resolve({ data: cart } as unknown as AxiosResponse));
    const getSalesChannelContextMock = jest.fn(() => Promise.resolve({ data: { id: '1' } } as unknown as AxiosResponse));
    const updateContextMock = jest.fn(() => Promise.resolve({ data: {} } as unknown as AxiosResponse));
    const checkoutMock = jest.fn(() => Promise.resolve());
    const removeLineItemsMock = jest.fn(() => Promise.resolve({ data: cart } as unknown as AxiosResponse));
    const saveLineItemMock = jest.fn(() => Promise.resolve({ data: cart } as unknown as AxiosResponse));
    const addMultipleLineItemsMock = jest.fn(() => Promise.resolve({ data: cart } as unknown as AxiosResponse));
    const modifyShippingCostsMock = jest.fn(() => Promise.resolve({ data: { data: cart } } as unknown as AxiosResponse));
    const transitionOrderTransactionStateMock = jest.fn(() => Promise.resolve());

    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('cartStoreService', (() => {
            return {
                createCart: createCartMock,
                getCart: getCartMock,
                cancelCart: cancelCartMock,
                addPromotionCode: addPromotionCodeMock,
                modifyShippingCosts: modifyShippingCostsMock,
                removeLineItems: removeLineItemsMock,
                saveLineItem: saveLineItemMock,
                addMultipleLineItems: addMultipleLineItemsMock,
            };
        }) as unknown as CartStoreService);
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('contextStoreService', (() => {
            return {
                getSalesChannelContext: getSalesChannelContextMock,
                updateContext: updateContextMock,
            };
        }) as unknown as ContextStoreService);
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('checkoutStoreService', (() => {
            return {
                checkout: checkoutMock,
            };
        }) as unknown as CheckoutStoreService);
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('orderStateMachineService', (() => {
            return {
                transitionOrderTransactionState: transitionOrderTransactionStateMock,
            };
        }) as unknown as OrderStateMachineApiService);
    });

    beforeEach(() => {
        Shopware.Store.get('swOrder').$reset();
    });

    it('should have an empty state', () => {
        expect(store).toEqual(
            expect.objectContaining({
                customer: null,
                defaultSalesChannel: null,
                cart: {
                    token: null,
                    lineItems: [],
                    price: {
                        totalPrice: null,
                    },
                    deliveries: [],
                } as unknown as Cart,
                context: {
                    token: '',
                    customer: null,
                    paymentMethod: {
                        translated: {
                            distinguishableName: '',
                        },
                    },
                    shippingMethod: {
                        translated: {
                            name: '',
                        },
                    },
                    currency: {
                        isoCode: 'EUR',
                        symbol: '€',
                        totalRounding: {
                            decimals: 2,
                        },
                    },
                    salesChannel: {
                        id: '',
                    },
                    context: {
                        currencyId: '',
                        languageIdChain: [],
                    },
                },
                promotionCodes: [],
                disabledAutoPromotion: false,
            }),
        );
    });

    it('returns if is customer active', () => {
        expect(store.isCustomerActive).toBe(false);

        store.setCustomer({
            id: '1',
            active: true,
        } as unknown as Entity<'customer'>);

        expect(store.isCustomerActive).toBe(true);
    });

    it('returns if cart token is available', () => {
        expect(store.isCartTokenAvailable).toBe(false);

        store.setCart({
            token: 'test',
            lineItems: [],
        } as unknown as Cart);

        expect(store.isCartTokenAvailable).toBe(true);
    });

    it('returns currency id', () => {
        expect(store.currencyId).toBe('');
        store.setContext({
            context: {
                context: {
                    currencyId: '1',
                    languageIdChain: [],
                },
            },
        } as unknown as SalesChannelContext);
    });

    it('returns invalid Promotion codes', () => {
        expect(store.invalidPromotionCodes).toEqual([]);
        store.setPromotionCodes([
            {
                discountId: 'test',
                code: 'test',
                isInvalid: true,
            },
            {
                discountId: 'test',
                code: 'test',
                isInvalid: false,
            },
        ]);

        expect(store.invalidPromotionCodes).toEqual([
            {
                discountId: 'test',
                code: 'test',
                isInvalid: true,
            },
        ]);
    });

    it('returns cart errors', () => {
        expect(store.cartErrors).toBeNull();
        store.setCart({
            lineItems: [],
            errors: [
                {
                    code: 'test',
                    status: 'test',
                },
                {
                    code: 'test',
                    status: 'test',
                },
            ],
        } as unknown as Cart);

        expect(store.cartErrors).toEqual([
            {
                code: 'test',
                status: 'test',
            },
            {
                code: 'test',
                status: 'test',
            },
        ]);
    });

    it('removes empty lines item', () => {
        store.setCart({
            lineItems: [
                { id: '1', quantity: 0 },
                { id: '2', quantity: 1 },
            ],
        } as unknown as Cart);

        expect(store.cart.lineItems).toHaveLength(2);

        store.removeEmptyLineItem('2');

        expect(store.cart.lineItems).toHaveLength(1);
        expect(store.cart.lineItems).toEqual(expect.arrayContaining([{ id: '1', quantity: 0 }]));
    });

    it('removes invalid promotion code', () => {
        store.setPromotionCodes([
            {
                discountId: 'test',
                code: 'test',
                isInvalid: true,
            },
            {
                discountId: 'test',
                code: 'test',
                isInvalid: false,
            },
        ]);

        expect(store.invalidPromotionCodes).toHaveLength(1);

        store.removeInvalidPromotionCodes();

        expect(store.invalidPromotionCodes).toHaveLength(0);
        expect(store.promotionCodes).toHaveLength(1);
    });

    it('selects existing customer', () => {
        store.selectExistingCustomer({
            customer: {
                id: '1',
                salesChannel: { id: '1' },
            } as unknown as Entity<'customer'>,
        });

        expect(store.customer).toEqual({
            id: '1',
            salesChannel: { id: '1' },
        });

        expect(store.defaultSalesChannel).toEqual({ id: '1' });
    });

    it('creates new cart', async () => {
        await store.createCart({ salesChannelId: '1' });

        expect(createCartMock).toHaveBeenLastCalledWith('1');
        expect(store.cart.token).toBe(token);
        expect(getSalesChannelContextMock).toHaveBeenLastCalledWith('1', token);
        expect(store.context).toEqual({ id: '1' });
    });

    it('gets cart', async () => {
        await store.getCart({ salesChannelId: '1', contextToken: token });

        expect(getCartMock).toHaveBeenLastCalledWith('1', token);
        expect(store.cart).toEqual({ token, lineItems: [] });
        expect(getSalesChannelContextMock).toHaveBeenLastCalledWith('1', token);
        expect(store.context).toEqual({ id: '1' });
    });

    it('cancels cart', async () => {
        await store.cancelCart({ salesChannelId: '1', contextToken: token });

        expect(cancelCartMock).toHaveBeenLastCalledWith('1', token);
    });

    it('updates order context', async () => {
        const context: ContextSwitchParameters = {
            currencyId: 'test',
            languageId: 'test',
            paymentMethodId: 'test',
            shippingMethodId: 'test',
            billingAddressId: 'test',
            shippingAddressId: 'test',
        };
        await store.updateOrderContext({ context, salesChannelId: '1', contextToken: token });

        expect(updateContextMock).toHaveBeenLastCalledWith(context, '1', token);
    });

    it('gets the context', async () => {
        await store.getContext({ salesChannelId: '1', contextToken: token });

        expect(getSalesChannelContextMock).toHaveBeenLastCalledWith('1', token);
    });

    it('saves order', async () => {
        await store.saveOrder({ salesChannelId: '1', contextToken: token });

        expect(checkoutMock).toHaveBeenLastCalledWith('1', token);
    });

    it('removes line items', async () => {
        await store.removeLineItems({ salesChannelId: '1', contextToken: token, lineItemKeys: ['1'] });

        expect(removeLineItemsMock).toHaveBeenLastCalledWith('1', token, ['1']);
        expect(store.cart).toEqual(cart);
    });

    it('saves line items', async () => {
        await store.saveLineItem({ salesChannelId: '1', contextToken: token, item });

        expect(saveLineItemMock).toHaveBeenLastCalledWith('1', token, item);
        expect(store.cart).toEqual(cart);
    });

    it('saves multiple lines items', async () => {
        await store.saveMultipleLineItems({ salesChannelId: '1', contextToken: token, items: [item] });

        expect(addMultipleLineItemsMock).toHaveBeenLastCalledWith('1', token, [item]);
        expect(store.cart).toEqual(cart);
    });

    it('adds promotion code', async () => {
        await store.addPromotionCode({ salesChannelId: '1', contextToken: token, code: 'testCode' });

        expect(addPromotionCodeMock).toHaveBeenLastCalledWith('1', token, 'testCode');
        expect(store.cart).toEqual(cart);
    });

    it('modifies shipping costs', async () => {
        await store.modifyShippingCosts({
            salesChannelId: '1',
            contextToken: token,
            shippingCosts: {
                unitPrice: 1,
                totalPrice: 10,
                calculatedTaxes: [],
                taxRules: [],
            },
        });

        expect(modifyShippingCostsMock).toHaveBeenLastCalledWith('1', token, {
            unitPrice: 1,
            totalPrice: 10,
            calculatedTaxes: [],
            taxRules: [],
        });
        expect(store.cart).toEqual(cart);
    });

    it('reminds payment', async () => {
        await store.remindPayment({ orderTransactionId: '1' });

        expect(transitionOrderTransactionStateMock).toHaveBeenLastCalledWith('1', 'remind');
    });
});
