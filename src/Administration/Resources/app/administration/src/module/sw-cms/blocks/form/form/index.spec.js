/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/form/form', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/form/form',
        name: 'form',
        component: 'sw-cms-block-form',
        preview: 'sw-cms-preview-form',
    });
});
