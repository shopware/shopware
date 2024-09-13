/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-slider', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-slider',
        name: 'image-slider',
        component: 'sw-cms-block-image-slider',
        preview: 'sw-cms-preview-image-slider',
    });
});
