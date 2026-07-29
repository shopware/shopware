/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/category-heading', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/category-heading',
        name: 'category-heading',
        component: 'sw-cms-block-category-heading',
        preview: 'sw-cms-preview-category-heading',
    });
});
