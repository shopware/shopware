/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-slider', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-slider',
        name: 'product-slider',
        component: 'sw-cms-el-product-slider',
        config: 'sw-cms-el-config-product-slider',
        preview: 'sw-cms-el-preview-product-slider',
    });
});
