/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/video/youtube-video', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/video/youtube-video',
        name: 'youtube-video',
        component: 'sw-cms-block-youtube-video',
        preview: 'sw-cms-preview-youtube-video',
    });
});
