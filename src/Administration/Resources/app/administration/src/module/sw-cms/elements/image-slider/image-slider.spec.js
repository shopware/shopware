/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/image-slider', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/image-slider',
        name: 'image-slider',
        component: 'sw-cms-el-image-slider',
        config: 'sw-cms-el-config-image-slider',
        preview: 'sw-cms-el-preview-image-slider',
    });
});
