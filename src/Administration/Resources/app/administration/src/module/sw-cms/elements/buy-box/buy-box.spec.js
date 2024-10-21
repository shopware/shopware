/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/buy-box', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/buy-box',
        name: 'buy-box',
        component: 'sw-cms-el-buy-box',
        config: 'sw-cms-el-config-buy-box',
        preview: 'sw-cms-el-preview-buy-box',
    });
});
