import { mergeTests } from '@playwright/test';

/**
 * Account
 */
import { Login } from './ShopCustomer/Account/Login';
import { Logout } from './ShopCustomer/Account/Logout';
import { Register } from './ShopCustomer/Account/Register';

/**
 * Cart
 */
import { AddPromotionCodeToCart } from './ShopCustomer/Cart/AddPromotionCodeToCart';
import { ProceedFromCartToCheckout } from './ShopCustomer/Cart/ProceedFromCartToCheckout';

/**
 * Checkout
 */
import { ConfirmTermsAndConditions } from './ShopCustomer/Checkout/ConfirmTermsAndConditions';
import { SelectStandardShippingOption } from './ShopCustomer/Checkout/SelectStandardShippingOption';
import { SelectExpressShippingOption } from './ShopCustomer/Checkout/SelectExpressShoppingOption';
import { SelectInvoicePaymentOption } from './ShopCustomer/Checkout/SelectInvoicePaymentOption';
import { SelectPaidInAdvancePaymentOption } from './ShopCustomer/Checkout/SelectPaidInAdvancePaymentOption';
import { SelectCashOnDeliveryPaymentOption } from './ShopCustomer/Checkout/SelectCashOnDeliveryPaymentOption';
import { SubmitOrder } from './ShopCustomer/Checkout/SubmitOrder';

/**
 * Product Detail
 */
import { AddProductToCart } from './ShopCustomer/ProductDetail/AddProductToCart';
import { ProceedFromProductToCheckout } from './ShopCustomer/ProductDetail/ProceedFromProductToCheckout';

/**
 * Search
 */
import { OpenSearchResultPage } from './ShopCustomer/Search/OpenSearchResultPage';
import { OpenSearchSuggestPage } from './ShopCustomer/Search/OpenSearchSuggestPage';

export const test = mergeTests(
    Login,
    Logout,
    Register,
    AddPromotionCodeToCart,
    ProceedFromCartToCheckout,
    ConfirmTermsAndConditions,
    SelectStandardShippingOption,
    SelectExpressShippingOption,
    SelectInvoicePaymentOption,
    SelectPaidInAdvancePaymentOption,
    SelectCashOnDeliveryPaymentOption,
    SubmitOrder,
    AddProductToCart,
    ProceedFromProductToCheckout,
    OpenSearchResultPage,
    OpenSearchSuggestPage,
);
