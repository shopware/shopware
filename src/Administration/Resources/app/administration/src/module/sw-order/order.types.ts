import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';

/**
 * @package customer-order
 */

interface TaxRule {
    taxRate: number,
    percentage: number | null,
}

enum LineItemType {
    PRODUCT = 'product',
    CREDIT = 'credit',
    CUSTOM = 'custom',
    PROMOTION = 'promotion',
}

enum PriceType {
    ABSOLUTE = 'absolute',
    QUANTITY = 'quantity',
}

interface CalculatedTax {
    id: string;
    taxRate: number;
    tax: number;
}

interface CalculatedPrice {
    unitPrice: number,
    totalPrice: number,
    calculatedTaxes: CalculatedTax[],
    taxRules: TaxRule[],
}

interface LineItem {
    id: string,
    versionId: string,
    label: string,
    description: string,
    type: LineItemType,
    payload: Record<string, unknown> | null,
    quantity: number,
    identifier: string,
    _isNew: boolean,
    price: CalculatedPrice | null,
    priceDefinition: {
        price: number,
        taxRules: TaxRule[],
        quantity: number,
        type: PriceType,
        isCalculated: boolean,
    },
    unitPrice: number,
    totalPrice: number,
    precision: number,
}

interface PromotionCodeTag {
    discountId: string,
    isInvalid: boolean,
    code: string,
}

interface CartError {
    level: number,
    message: string,
}

interface CartDelivery {
    id: string;
    deliveryDate: {
        earliest: string,
    }
    shippingCosts: CalculatedPrice,
    shippingMethod: Entity<'shipping_method'>,
}

interface Cart {
    token: string,
    lineItems: Array<LineItem>,
    errors: Array<CartError>,
    deliveries: CartDelivery[],
    price: {
        rawTotal: number,
        totalPrice: number,
        calculatedTaxes: CalculatedTax[],
        taxStatus: string
    }
}

interface Context {
    currencyId: string,
    languageIdChain: Array<string>,
}

interface SalesChannelContext {
    token: string,
    customer: Entity<'customer'> | null,
    paymentMethod: Entity<'payment_method'>,
    shippingMethod: Entity<'shipping_method'>,
    currency: Entity<'currency'>,
    context: Context,
    salesChannel: Entity<'sales_channel'>,
}

interface ContextSwitchParameters {
    currencyId: string | null,
    languageId: string | null,
    paymentMethodId: string | null,
    shippingMethodId: string | null,
    billingAddressId: string | null,
    shippingAddressId: string | null,
}

/**
 * @private
 */
export type {
    CalculatedPrice,
    CalculatedTax,
    Cart,
    CartError,
    CartDelivery,
    ContextSwitchParameters,
    LineItem,
    PromotionCodeTag,
    SalesChannelContext,
};

/**
 * @private
 */
export {
    LineItemType,
    PriceType,
};
