/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/text', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/text',
        name: 'text',
        component: 'sw-cms-el-text',
        config: 'sw-cms-el-config-text',
        preview: 'sw-cms-el-preview-text',
    });
});
