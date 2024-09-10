/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text',
        name: 'text',
        component: 'sw-cms-block-text',
        preview: 'sw-cms-preview-text',
    });
});
