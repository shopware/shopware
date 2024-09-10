/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/cross-selling', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/cross-selling',
        name: 'cross-selling',
        component: 'sw-cms-el-cross-selling',
        config: 'sw-cms-el-config-cross-selling',
        preview: 'sw-cms-el-preview-cross-selling',
    });
});
