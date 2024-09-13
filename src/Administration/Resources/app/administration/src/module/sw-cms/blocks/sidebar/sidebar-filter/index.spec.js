/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/sidebar/sidebar-filter', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/sidebar/sidebar-filter',
        name: 'sidebar-filter',
        component: 'sw-cms-block-sidebar-filter',
        preview: 'sw-cms-preview-sidebar-filter',
    });
});
