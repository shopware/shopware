/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/product-three-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/product-three-column',
        name: 'product-three-column',
        component: 'sw-cms-block-product-three-column',
        preview: 'sw-cms-preview-product-three-column',
    });
});
