/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/sidebar-filter', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/sidebar-filter',
        name: 'sidebar-filter',
        component: 'sw-cms-el-sidebar-filter',
        config: 'sw-cms-el-config-sidebar-filter',
        preview: 'sw-cms-el-preview-sidebar-filter',
    });
});
