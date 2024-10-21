/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/image-text-gallery', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/image-text-gallery',
        name: 'image-text-gallery',
        component: 'sw-cms-block-image-text-gallery',
        preview: 'sw-cms-preview-image-text-gallery',
    });
});
