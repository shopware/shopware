/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/cross-selling', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/cross-selling',
        name: 'cross-selling',
        component: 'sw-cms-block-cross-selling',
        preview: 'sw-cms-preview-cross-selling',
    });
});
