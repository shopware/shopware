/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/product-description-reviews', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/product-description-reviews',
        name: 'product-description-reviews',
        component: 'sw-cms-block-product-description-reviews',
        preview: 'sw-cms-preview-product-description-reviews',
    });
});
