/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/sidebar-category-navigation', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/sidebar-category-navigation',
        name: 'category-navigation',
        component: 'sw-cms-el-category-navigation',
        config: 'sw-cms-el-config-category-navigation',
        preview: 'sw-cms-el-preview-category-navigation',
    });
});
