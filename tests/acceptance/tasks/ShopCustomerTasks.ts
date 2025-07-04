import { mergeTests } from '@playwright/test';

/**
 * Account Orders
 */
import { DownloadDigitalProductFromOrderAndExpectContentToBe } from './ShopCustomer/Account/DownloadDigitalProductFromOrder';

/**
 * Cart
 */
import { AddPromotionCodeToCart } from './ShopCustomer/Cart/AddPromotionCodeToCart';

/**
 * Checkout
 */
import { ConfirmImmediateAccessToDigitalProduct } from './ShopCustomer/Checkout/ConfirmImmediateAccessToDigitalProduct';

/**
 * PageSpeed & Accessibility
 */
import { ValidateLighthouseScore } from './ShopCustomer/Pagespeed/ValidateLighthouseScore';
import { CheckVisibilityInHome } from './ShopCustomer/Listing/CheckVisibilityInHome';

export const test = mergeTests(
    DownloadDigitalProductFromOrderAndExpectContentToBe,
    AddPromotionCodeToCart,
    ConfirmImmediateAccessToDigitalProduct,
    ValidateLighthouseScore,
    CheckVisibilityInHome,
);

