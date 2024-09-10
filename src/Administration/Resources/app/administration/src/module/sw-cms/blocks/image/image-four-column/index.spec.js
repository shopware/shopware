/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-four-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-four-column',
        name: 'image-four-column',
        component: 'sw-cms-block-image-four-column',
        preview: 'sw-cms-preview-image-four-column',
    });
});
