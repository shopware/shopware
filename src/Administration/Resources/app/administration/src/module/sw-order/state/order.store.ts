import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { Module } from 'vuex';
import type { AxiosResponse } from 'axios';
import type {
    CalculatedPrice,
    Cart,
    CartError,
    ContextSwitchParameters,
    LineItem,
    PromotionCodeTag,
    SalesChannelContext,
} from '../order.types';

/**
 * @package customer-order
 */

const { Service } = Shopware;

function filterEmptyLineItems(items: LineItem[]) {
    return items.filter(item => item.label === '');
}

function reverseLineItems(items: LineItem[]) {
    return items.slice().reverse();
}

function mergeEmptyAndExistingLineItems(emptyLineItems: LineItem[], lineItems: LineItem[]) {
    // Reverse the lineItems so the newly added are at the top for better UX
    reverseLineItems(lineItems);

    return [...emptyLineItems, ...lineItems];
}

interface SwOrderState {
    cart: Cart;
    disabledAutoPromotion: boolean,
    promotionCodes: PromotionCodeTag[],
    defaultSalesChannel: Entity<'sales_channel'> | null,
    context: SalesChannelContext,
    customer: Entity<'customer'> | null,
}

const SwOrderStore: Module<SwOrderState, VuexRootState> = {
    namespaced: true,

    state: (): SwOrderState => ({
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
            } as Entity<'payment_method'>,
            shippingMethod: {
                translated: {
                    name: '',
                },
            } as Entity<'shipping_method'>,
            currency: {
                shortName: 'EUR',
                symbol: '€',
                totalRounding: {
                    decimals: 2,
                },
            } as Entity<'currency'>,
            salesChannel: {
                id: '',
            } as Entity<'sales_channel'>,
            context: {
                currencyId: '',
                languageIdChain: [],
            },
        },
        promotionCodes: [],
        disabledAutoPromotion: false,
    }),

    mutations: {
        setCustomer(state: SwOrderState, customer: Entity<'customer'>) {
            state.context.customer = customer;
            state.customer = customer;
        },

        setDefaultSalesChannel(state: SwOrderState, salesChannel: Entity<'sales_channel'>) {
            state.defaultSalesChannel = salesChannel;
        },

        setCartToken(state: SwOrderState, token: string) {
            state.cart.token = token;
        },

        setCart(state: SwOrderState, cart: Cart) {
            const emptyLineItems = filterEmptyLineItems(state.cart.lineItems);
            state.cart = cart;
            state.cart.lineItems = mergeEmptyAndExistingLineItems(emptyLineItems, state.cart.lineItems);
        },

        setCartLineItems(state: SwOrderState, lineItems: LineItem[]) {
            state.cart.lineItems = lineItems;
        },

        setCurrency(state: SwOrderState, currency: Entity<'currency'>) {
            state.context.currency = currency;
        },

        setContext(state: SwOrderState, context: SalesChannelContext) {
            state.context = context;
        },

        setPromotionCodes(state: SwOrderState, promotionCodes: PromotionCodeTag[]) {
            state.promotionCodes = promotionCodes;
        },

        removeEmptyLineItem(state: SwOrderState, emptyLineItemKey: string) {
            state.cart.lineItems = state.cart.lineItems.filter(item => item.id !== emptyLineItemKey);
        },

        removeInvalidPromotionCodes(state: SwOrderState) {
            state.promotionCodes = state.promotionCodes.filter(item => !item.isInvalid);
        },

        setDisabledAutoPromotion(state: SwOrderState, disabledAutoPromotion: boolean) {
            state.disabledAutoPromotion = disabledAutoPromotion;
        },
    },

    getters: {
        isCustomerActive(state: SwOrderState): boolean {
            return !!state?.context.customer?.active;
        },

        isCartTokenAvailable(state: SwOrderState): boolean {
            return !!state?.cart?.token;
        },

        currencyId(state: SwOrderState): string {
            return state?.context.context.currencyId ?? '';
        },

        invalidPromotionCodes(state: SwOrderState): PromotionCodeTag[] {
            return state.promotionCodes.filter(item => item.isInvalid);
        },

        cartErrors(state: SwOrderState): CartError[] {
            return state?.cart?.errors ?? null;
        },
    },

    actions: {
        selectExistingCustomer({ commit }, { customer }: { customer: Entity<'customer'> }) {
            commit('setCustomer', customer);
            commit('setDefaultSalesChannel', { ...(customer?.salesChannel ?? null) });
        },

        createCart({ commit }, { salesChannelId }: { salesChannelId: string }) {
            return Service('cartStoreService')
                .createCart(salesChannelId)
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                .then((response: AxiosResponse): string => {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    const token = response.data.token as string;
                    commit('setCartToken', token);
                    return token;
                })
                .then((contextToken) => {
                    return Service('contextStoreService')
                        .getSalesChannelContext(salesChannelId, contextToken)
                        .then((response: AxiosResponse) => commit('setContext', response.data));
                });
        },

        getCart({ commit }, { salesChannelId, contextToken }: { salesChannelId: string, contextToken: string }) {
            if ((`${contextToken}`).length !== 32) {
                throw new Error('Invalid context token');
            }

            return Promise.all([
                Service('cartStoreService')
                    .getCart(salesChannelId, contextToken)
                    .then((response: AxiosResponse) => commit('setCart', response.data)),
                Service('contextStoreService')
                    .getSalesChannelContext(salesChannelId, contextToken)
                    .then((response: AxiosResponse) => commit('setContext', response.data)),
            ]);
        },

        cancelCart(_, { salesChannelId, contextToken }: { salesChannelId: string, contextToken: string }) {
            if ((`${contextToken}`).length !== 32) {
                throw new Error('Invalid context token');
            }

            return Service('cartStoreService').cancelCart(salesChannelId, contextToken);
        },

        updateCustomerContext(_, { customerId, salesChannelId, contextToken }:
            { customerId: string, salesChannelId: string, contextToken: string }) {
            return Service('contextStoreService')
                .updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext(_, { context, salesChannelId, contextToken }:
            { context: ContextSwitchParameters, salesChannelId: string, contextToken: string }) {
            return Service('contextStoreService')
                .updateContext(context, salesChannelId, contextToken);
        },

        getContext(_, { salesChannelId, contextToken }: { salesChannelId: string, contextToken: string }) {
            return Service('contextStoreService')
                .getSalesChannelContext(salesChannelId, contextToken);
        },

        saveOrder(_, { salesChannelId, contextToken }: { salesChannelId: string, contextToken: string }) {
            return Service('checkoutStoreService')
                .checkout(salesChannelId, contextToken);
        },

        removeLineItems(
            { commit },
            { salesChannelId, contextToken, lineItemKeys }:
                { salesChannelId: string, contextToken: string, lineItemKeys: string[] },
        ) {
            return Service('cartStoreService')
                .removeLineItems(salesChannelId, contextToken, lineItemKeys)
                .then((response: AxiosResponse) => commit('setCart', response.data));
        },

        saveLineItem(
            { commit },
            { salesChannelId, contextToken, item }: { salesChannelId: string, contextToken: string, item: LineItem },
        ) {
            return Service('cartStoreService')
                .saveLineItem(salesChannelId, contextToken, item)
                .then((response: AxiosResponse) => commit('setCart', response.data));
        },

        saveMultipleLineItems(
            { commit },
            { salesChannelId, contextToken, items }: { salesChannelId: string, contextToken: string, items: LineItem[] },
        ) {
            return Service('cartStoreService')
                .addMultipleLineItems(salesChannelId, contextToken, items)
                .then((response: AxiosResponse) => commit('setCart', response.data));
        },

        addPromotionCode(
            { commit },
            { salesChannelId, contextToken, code }: { salesChannelId: string, contextToken: string, code: string },
        ): Promise<void> {
            return Service('cartStoreService')
                .addPromotionCode(salesChannelId, contextToken, code)
                .then(response => commit('setCart', response.data));
        },

        modifyShippingCosts(
            { commit },
            { salesChannelId, contextToken, shippingCosts }:
                { salesChannelId: string, contextToken: string, shippingCosts: CalculatedPrice },
        ) {
            return Service('cartStoreService')
                .modifyShippingCosts(salesChannelId, contextToken, shippingCosts)
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                .then((response: AxiosResponse) => commit('setCart', response.data.data));
        },

        remindPayment(_, { orderTransactionId }: { orderTransactionId: string }) {
            return Service('orderStateMachineService')
                .transitionOrderTransactionState(orderTransactionId, 'remind');
        },
    },
};

/**
 * @private
 */
export default SwOrderStore;

/**
 * @private
 */
export type {
    SwOrderState,
};

