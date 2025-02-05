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
 * @sw-package checkout
 */

const { Service } = Shopware;

function filterEmptyLineItems(items: LineItem[]) {
    return items.filter((item) => item.label === '');
}

function reverseLineItems(items: LineItem[]) {
    return items.slice().reverse();
}

function mergeEmptyAndExistingLineItems(emptyLineItems: LineItem[], lineItems: LineItem[]) {
    // Reverse the lineItems so the newly added are at the top for better UX
    reverseLineItems(lineItems);

    return [
        ...emptyLineItems,
        ...lineItems,
    ];
}

interface SwOrderState {
    cart: Cart;
    disabledAutoPromotion: boolean;
    promotionCodes: PromotionCodeTag[];
    defaultSalesChannel: Entity<'sales_channel'> | null;
    context: SalesChannelContext;
    customer: Entity<'customer'> | null;
}

const swOrderStore = Shopware.Store.register({
    id: 'swOrder',

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
                isoCode: 'EUR',
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
            return state.promotionCodes.filter((item) => item.isInvalid);
        },

        cartErrors(state: SwOrderState): CartError[] {
            return state?.cart?.errors ?? null;
        },
    },

    actions: {
        setCustomer(customer: Entity<'customer'> | null) {
            this.context.customer = customer;
            this.customer = customer;
        },

        setDefaultSalesChannel(salesChannel: Entity<'sales_channel'> | null) {
            this.defaultSalesChannel = salesChannel;
        },

        setCartToken(token: string) {
            this.cart.token = token;
        },

        setCart(cart: Cart) {
            const emptyLineItems = filterEmptyLineItems(this.cart.lineItems);
            this.cart = cart;
            this.cart.lineItems = mergeEmptyAndExistingLineItems(emptyLineItems, this.cart.lineItems);
        },

        setCartLineItems(lineItems: LineItem[]) {
            this.cart.lineItems = lineItems;
        },

        setCurrency(currency: Entity<'currency'>) {
            this.context.currency = currency;
        },

        setContext(context: SalesChannelContext) {
            this.context = context;
        },

        setPromotionCodes(promotionCodes: PromotionCodeTag[]) {
            this.promotionCodes = promotionCodes;
        },

        removeEmptyLineItem(emptyLineItemKey: string) {
            this.cart.lineItems = this.cart.lineItems.filter((item) => item.id !== emptyLineItemKey);
        },

        removeInvalidPromotionCodes() {
            this.promotionCodes = this.promotionCodes.filter((item) => !item.isInvalid);
        },

        setDisabledAutoPromotion(disabledAutoPromotion: boolean) {
            this.disabledAutoPromotion = disabledAutoPromotion;
        },

        selectExistingCustomer({ customer }: { customer: Entity<'customer'> | null }) {
            this.setCustomer(customer);
            this.setDefaultSalesChannel(customer?.salesChannel ?? null);
        },

        createCart({ salesChannelId }: { salesChannelId: string }) {
            return (
                Service('cartStoreService')
                    .createCart(salesChannelId)
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    .then((response: AxiosResponse): string => {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                        const token = response.data.token as string;
                        this.setCartToken(token);
                        return token;
                    })
                    .then((contextToken) => {
                        return Service('contextStoreService')
                            .getSalesChannelContext(salesChannelId, contextToken)
                            .then((response: AxiosResponse) => this.setContext(response.data as SalesChannelContext));
                    })
            );
        },

        getCart({ salesChannelId, contextToken }: { salesChannelId: string; contextToken: string }) {
            if (`${contextToken}`.length !== 32) {
                throw new Error('Invalid context token');
            }

            return Promise.all([
                Service('cartStoreService')
                    .getCart(salesChannelId, contextToken)
                    .then((response: AxiosResponse) => this.setCart(response.data as Cart)),
                Service('contextStoreService')
                    .getSalesChannelContext(salesChannelId, contextToken)
                    .then((response: AxiosResponse) => this.setContext(response.data as SalesChannelContext)),
            ]);
        },

        cancelCart({ salesChannelId, contextToken }: { salesChannelId: string; contextToken: string }) {
            if (`${contextToken}`.length !== 32) {
                throw new Error('Invalid context token');
            }

            return Service('cartStoreService').cancelCart(salesChannelId, contextToken);
        },

        updateCustomerContext({
            customerId,
            salesChannelId,
            contextToken,
        }: {
            customerId: string;
            salesChannelId: string;
            contextToken: string;
        }) {
            return Service('contextStoreService').updateCustomerContext(customerId, salesChannelId, contextToken);
        },

        updateOrderContext({
            context,
            salesChannelId,
            contextToken,
        }: {
            context: ContextSwitchParameters;
            salesChannelId: string;
            contextToken: string;
        }) {
            return Service('contextStoreService').updateContext(context, salesChannelId, contextToken);
        },

        getContext({ salesChannelId, contextToken }: { salesChannelId: string; contextToken: string }) {
            return Service('contextStoreService').getSalesChannelContext(salesChannelId, contextToken);
        },

        saveOrder({ salesChannelId, contextToken }: { salesChannelId: string; contextToken: string }) {
            return Service('checkoutStoreService').checkout(salesChannelId, contextToken);
        },

        removeLineItems({
            salesChannelId,
            contextToken,
            lineItemKeys,
        }: {
            salesChannelId: string;
            contextToken: string;
            lineItemKeys: string[];
        }) {
            return Service('cartStoreService')
                .removeLineItems(salesChannelId, contextToken, lineItemKeys)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        saveLineItem({
            salesChannelId,
            contextToken,
            item,
        }: {
            salesChannelId: string;
            contextToken: string;
            item: LineItem;
        }) {
            return Service('cartStoreService')
                .saveLineItem(salesChannelId, contextToken, item)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        saveMultipleLineItems({
            salesChannelId,
            contextToken,
            items,
        }: {
            salesChannelId: string;
            contextToken: string;
            items: LineItem[];
        }) {
            return Service('cartStoreService')
                .addMultipleLineItems(salesChannelId, contextToken, items)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        addPromotionCode({
            salesChannelId,
            contextToken,
            code,
        }: {
            salesChannelId: string;
            contextToken: string;
            code: string;
        }): Promise<void> {
            return Service('cartStoreService')
                .addPromotionCode(salesChannelId, contextToken, code)
                .then((response) => this.setCart(response.data as Cart));
        },

        modifyShippingCosts({
            salesChannelId,
            contextToken,
            shippingCosts,
        }: {
            salesChannelId: string;
            contextToken: string;
            shippingCosts: CalculatedPrice;
        }) {
            return (
                Service('cartStoreService')
                    ?.modifyShippingCosts(salesChannelId, contextToken, shippingCosts)
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    .then((response: AxiosResponse) => this.setCart(response.data.data as Cart))
            );
        },

        remindPayment({ orderTransactionId }: { orderTransactionId: string }) {
            return Service('orderStateMachineService').transitionOrderTransactionState(orderTransactionId, 'remind');
        },
    },
});

/**
 * @private
 */
export default swOrderStore;

/**
 * @private
 */
export type SwOrderStore = ReturnType<typeof swOrderStore>;

/**
 * @private
 */
export type { SwOrderState };
