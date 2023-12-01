import { mergeTests } from '@playwright/test';

/**
 * Product
 */
import { GenerateVariants } from './ShopAdmin/Product/GenerateVariants';

export const test = mergeTests(
    GenerateVariants,
);
