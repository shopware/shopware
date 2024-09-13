/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/image', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/image',
        name: 'image',
        component: 'sw-cms-el-image',
        config: 'sw-cms-el-config-image',
        preview: 'sw-cms-el-preview-image',
    });
});
