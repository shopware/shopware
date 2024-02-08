import { mergeTests } from '@playwright/test';
import { ProductData } from './Product/Product.data';
import { PropertiesData } from './Product/Properties.data';
import { CartWithProductData } from './Checkout/CartWithProduct.data';
import { PromotionWithCodeData } from './Checkout/PromotionWithCode.data';
import { components } from '@shopware/api-client/admin-api-types';

export interface DataFixtures {
    productData: components['schemas']['Product'],
    promotionWithCodeData: components['schemas']['Promotion'],
    cartWithProductData,
    propertiesData: {
        propertyGroupColor: components['schemas']['PropertyGroup']
        propertyGroupSize: components['schemas']['PropertyGroup']
    },
}

export const test = mergeTests(
    ProductData,
    CartWithProductData,
    PromotionWithCodeData,
    PropertiesData
);
