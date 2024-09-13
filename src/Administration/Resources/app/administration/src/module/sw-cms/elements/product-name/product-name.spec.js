/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-name', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-name',
        name: 'product-name',
        component: 'sw-cms-el-product-name',
        config: 'sw-cms-el-config-product-name',
    });
});
