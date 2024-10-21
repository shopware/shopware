/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-two-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-two-column',
        name: 'image-two-column',
        component: 'sw-cms-block-image-two-column',
        preview: 'sw-cms-preview-image-two-column',
    });
});
