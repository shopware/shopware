/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/youtube-video', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/youtube-video',
        name: 'youtube-video',
        component: 'sw-cms-el-youtube-video',
        config: 'sw-cms-el-config-youtube-video',
        preview: 'sw-cms-el-preview-youtube-video',
    });
});
