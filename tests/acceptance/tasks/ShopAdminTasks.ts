import { mergeTests } from '@playwright/test';

/**
 * Media
 */
import { UploadImage } from './ShopAdmin/Product/UploadImage';

/**
 * Product
 */
import { GenerateVariants } from './ShopAdmin/Product/GenerateVariants';
import { SaveProduct } from './ShopAdmin/Product/SaveProduct';

export const test = mergeTests(
    GenerateVariants,
    SaveProduct,
    UploadImage,
);
