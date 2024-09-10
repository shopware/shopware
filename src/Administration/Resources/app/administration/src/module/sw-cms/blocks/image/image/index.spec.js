/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image',
        name: 'image',
        component: 'sw-cms-block-image',
        preview: 'sw-cms-preview-image',
    });
});
