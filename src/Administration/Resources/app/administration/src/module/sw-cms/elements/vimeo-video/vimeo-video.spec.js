/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/vimeo-video', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/vimeo-video',
        name: 'vimeo-video',
        component: 'sw-cms-el-vimeo-video',
        config: 'sw-cms-el-config-vimeo-video',
        preview: 'sw-cms-el-preview-vimeo-video',
    });
});
