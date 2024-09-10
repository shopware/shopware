/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-gallery', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-gallery',
        name: 'image-gallery',
        component: 'sw-cms-block-image-gallery',
        preview: 'sw-cms-preview-image-gallery',
    });
});
