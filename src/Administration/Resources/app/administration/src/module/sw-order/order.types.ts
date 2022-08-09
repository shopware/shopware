import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';

interface PaymentMethod extends Entity {
    translated: {
        name: string,
        distinguishableName: string,
    }
}

interface ShippingMethod extends Entity {
    translated: {
        name: string,
    }
}

interface SalesChannel extends Entity {
    id: string,
    active: boolean,
    currencyId: string,
}

interface Country extends Entity {
    translated: {
        name: string,
    },
}

interface CountryState extends Entity {
    translated: {
        name: string,
    },
}

interface CustomerAddress extends Entity {
    id: string,
    street: string,
    zipcode: string,
    city: string,
    countryState: CountryState | null,
    country: Country | null,
    phoneNumber: string,
    hidden: boolean | undefined,
}

interface Customer extends Entity {
    id: string,
    active: boolean,
    salesChannelId: string,
    firstName: string,
    lastName: string,
    email: string,
    defaultPaymentMethod: PaymentMethod | null,
    defaultBillingAddress: CustomerAddress | null,
    defaultShippingAddress: CustomerAddress | null,
    activeBillingAddress: CustomerAddress | null,
    activeShippingAddress: CustomerAddress | null,
    addresses: EntityCollection,
    salesChannel: SalesChannel | null,
    customerNumber: string,
}

interface Currency extends Entity {
    id: string,
    isoCode: string,
    shortName: string,
    symbol: string,
    totalRounding: {
        decimals: number,
    }
}

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
    shippingMethod: ShippingMethod,
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
    customer: Customer | null,
    paymentMethod: PaymentMethod,
    shippingMethod: ShippingMethod,
    currency: Currency,
    context: Context,
    salesChannel: SalesChannel,
}

interface ContextSwitchParameters {
    currencyId: string | null,
    languageId: string | null,
    paymentMethodId: string | null,
    shippingMethodId: string | null,
    billingAddressId: string | null,
    shippingAddressId: string | null,
}

interface StateMachineState extends Entity {
    name: string,
    technicalName: string,
    translated: {
        name: string
    },
    stateMachineId: string
}

interface StateMachineHistory extends Entity {
    fromStateMachineState: StateMachineState,
    toStateMachineState: StateMachineState,
    translated: {
        name: string
    },
    createdAt: Date,
    user: {
        username: string,
    },
    entityName: 'order' | 'order_delivery' | 'order_transaction',
}

interface OrderDelivery extends Entity {
    id: string,
    createdAt: Date,
    shippingCosts: CalculatedPrice,
    shippingMethod: ShippingMethod,
    stateMachineState: StateMachineState,
}

interface OrderPayment extends Entity {
    id: string,
    createdAt: Date,
    paymentMethod: PaymentMethod,
    stateMachineState: StateMachineState,
}

interface OrderDeliveryCollection extends Array<OrderDelivery> {
    first: () => OrderPayment|null,
}

interface OrderTransactionCollection extends Array<OrderPayment> {
    last: () => OrderPayment|null,
}

interface Order extends Entity {
    id: string,
    orderNumber: string,
    lineItems: LineItem[],
    orderDateTime: Date,
    createdAt: Date,
    user: {
        username: string,
    },
    stateMachineState: StateMachineState,
    deliveries: OrderDeliveryCollection,
    transactions: OrderTransactionCollection,
}

/**
 * @private
 */
export type {
    Order,
    OrderDelivery,
    OrderPayment,
    CalculatedPrice,
    CalculatedTax,
    Cart,
    CartError,
    CartDelivery,
    Currency,
    Customer,
    CustomerAddress,
    ContextSwitchParameters,
    LineItem,
    PaymentMethod,
    PromotionCodeTag,
    SalesChannel,
    SalesChannelContext,
    ShippingMethod,
    StateMachineState,
    StateMachineHistory,
};

/**
 * @private
 */
export {
    LineItemType,
    PriceType,
};
