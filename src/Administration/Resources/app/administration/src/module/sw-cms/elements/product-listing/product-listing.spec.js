/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-listing', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-listing',
        name: 'product-listing',
        component: 'sw-cms-el-product-listing',
        config: 'sw-cms-el-config-product-listing',
        preview: 'sw-cms-el-preview-product-listing',
    });
});
