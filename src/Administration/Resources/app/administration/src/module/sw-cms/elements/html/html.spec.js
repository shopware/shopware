/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/html', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/html',
        name: 'html',
        component: 'sw-cms-el-html',
        config: 'sw-cms-el-config-html',
        preview: 'sw-cms-el-preview-html',
    });
});
