/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/category-name', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/category-name',
        name: 'category-name',
        component: 'sw-cms-el-category-name',
        config: 'sw-cms-el-config-category-name',
    });
});
