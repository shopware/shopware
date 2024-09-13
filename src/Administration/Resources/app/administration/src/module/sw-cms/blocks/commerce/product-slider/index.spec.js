/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/product-slider', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/product-slider',
        name: 'product-slider',
        component: 'sw-cms-block-product-slider',
        preview: 'sw-cms-preview-product-slider',
    });
});
