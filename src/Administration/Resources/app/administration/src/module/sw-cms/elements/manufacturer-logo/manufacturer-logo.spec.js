/**
 * @package buyers-experience
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/manufacturer-logo', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/manufacturer-logo',
        name: 'manufacturer-logo',
        component: 'sw-cms-el-manufacturer-logo',
        config: 'sw-cms-el-config-manufacturer-logo',
    });
});
