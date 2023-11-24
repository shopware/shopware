import { mergeTests } from '@playwright/test';
import { ProductData } from './Product/Product.data';
import { CartWithProductData } from './Checkout/CartWithProduct.data';
import { PromotionWithCodeData } from './Checkout/PromotionWithCode.data';
import { components } from '@shopware/api-client/admin-api-types';

export interface DataFixtures {
    productData: components['schemas']['Product'],
    promotionWithCodeData: components['schemas']['Promotion'],
}

export const test = mergeTests(
    ProductData,
    CartWithProductData,
    PromotionWithCodeData
);
