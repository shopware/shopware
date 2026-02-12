/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/video', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/video',
        name: 'video',
        component: 'sw-cms-el-video',
        config: 'sw-cms-el-config-video',
        preview: 'sw-cms-el-preview-video',
    });
});
