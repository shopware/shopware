import { mergeTests } from '@playwright/test';
import { ProductData } from './Product/Product.data';
import { DigitalProductData } from './Product/DigitalProduct.data';
import { PropertiesData } from './Product/Properties.data';
import { CartWithProductData } from './Checkout/CartWithProduct.data';
import { PromotionWithCodeData } from './Checkout/PromotionWithCode.data';
import { components } from '@shopware/api-client/admin-api-types';
import { MediaData } from './Media/Media.data';
import { OrderData } from './Order/Order.data';
import { TagData } from './Tag/Tag.data';

export interface DataFixtures {
    productData: components['schemas']['Product'],
    digitalProductData: { 
        product: components['schemas']['Product'],
        fileContent: string
    }, 
    promotionWithCodeData: components['schemas']['Promotion'],
    cartWithProductData,
    propertiesData: {
        propertyGroupColor: components['schemas']['PropertyGroup']
        propertyGroupSize: components['schemas']['PropertyGroup']
    },
    mediaData: components['schemas']['Media'],
    orderData: components['schemas']['Order'],
    tagData: components['schemas']['Tag'],
}

export const test = mergeTests(
    ProductData,
    DigitalProductData,
    CartWithProductData,
    PromotionWithCodeData,
    PropertiesData,
    MediaData,
    OrderData,
    TagData,
);
