/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-box', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-box',
        name: 'product-box',
        component: 'sw-cms-el-product-box',
        config: 'sw-cms-el-config-product-box',
        preview: 'sw-cms-el-preview-product-box',
    });
});
