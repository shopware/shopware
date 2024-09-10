/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/center-text', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/center-text',
        name: 'center-text',
        component: 'sw-cms-block-center-text',
        preview: 'sw-cms-preview-center-text',
    });
});
