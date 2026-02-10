/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/video/video', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/video/video',
        name: 'video',
        component: 'sw-cms-block-video',
        preview: 'sw-cms-preview-video',
    });
});
