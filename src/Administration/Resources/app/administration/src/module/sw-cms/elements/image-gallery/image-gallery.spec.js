/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/image-gallery', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/image-gallery',
        name: 'image-gallery',
        component: 'sw-cms-el-image-gallery',
        config: 'sw-cms-el-config-image-gallery',
        preview: 'sw-cms-el-preview-image-gallery',
    });
});
