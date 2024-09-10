/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-description-reviews', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-description-reviews',
        name: 'product-description-reviews',
        component: 'sw-cms-el-product-description-reviews',
        config: 'sw-cms-el-config-product-description-reviews',
        preview: 'sw-cms-el-preview-product-description-reviews',
    });
});
