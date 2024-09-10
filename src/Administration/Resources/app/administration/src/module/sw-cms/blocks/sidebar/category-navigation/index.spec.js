/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/sidebar/category-navigation', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/sidebar/category-navigation',
        name: 'category-navigation',
        component: 'sw-cms-block-category-navigation',
        preview: 'sw-cms-preview-category-navigation',
    });
});
