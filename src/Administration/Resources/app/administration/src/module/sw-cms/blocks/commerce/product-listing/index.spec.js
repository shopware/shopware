/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/product-listing', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/product-listing',
        name: 'product-listing',
        component: 'sw-cms-block-product-listing',
        preview: 'sw-cms-preview-product-listing',
    });
});
