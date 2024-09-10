/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/video/vimeo-video', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/video/vimeo-video',
        name: 'vimeo-video',
        component: 'sw-cms-block-vimeo-video',
        preview: 'sw-cms-preview-vimeo-video',
    });
});
