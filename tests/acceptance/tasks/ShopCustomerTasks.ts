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
import { CheckVisibilityInHome } from './ShopCustomer/Listing/CheckVisibilityInHome';
/**
 * PageSpeed & Accessibility
 */
import { ValidateLighthouseScore } from './ShopCustomer/Pagespeed/ValidateLighthouseScore';

/**
 * Settings
 */
import { AcceptTechnicalRequiredCookies } from './ShopCustomer/Settings/AcceptTechnicalRequiredCookies';

export const test = mergeTests(
    DownloadDigitalProductFromOrderAndExpectContentToBe,
    AddPromotionCodeToCart,
    ConfirmImmediateAccessToDigitalProduct,
    ValidateLighthouseScore,
    CheckVisibilityInHome,
    AcceptTechnicalRequiredCookies,
);

