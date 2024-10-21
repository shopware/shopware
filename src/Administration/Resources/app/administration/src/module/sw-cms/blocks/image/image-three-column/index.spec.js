/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-three-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-three-column',
        name: 'image-three-column',
        component: 'sw-cms-block-image-three-column',
        preview: 'sw-cms-preview-image-three-column',
    });
});
