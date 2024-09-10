/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text-two-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text-two-column',
        name: 'text-two-column',
        component: 'sw-cms-block-text-two-column',
        preview: 'sw-cms-preview-text-two-column',
    });
});
