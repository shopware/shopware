/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/product-heading', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/product-heading',
        name: 'product-heading',
        component: 'sw-cms-block-product-heading',
        preview: 'sw-cms-preview-product-heading',
    });
});
