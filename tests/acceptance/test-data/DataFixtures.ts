import { mergeTests } from '@playwright/test';
import { CartWithProductData } from './Checkout/CartWithProduct.data';
import { PromotionWithCodeData } from './Checkout/PromotionWithCode.data';

export const test = mergeTests(
    CartWithProductData,
    PromotionWithCodeData
);
