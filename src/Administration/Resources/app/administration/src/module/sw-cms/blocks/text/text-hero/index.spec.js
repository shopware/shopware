/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text-hero', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text-hero',
        name: 'text-hero',
        component: 'sw-cms-block-text-hero',
        preview: 'sw-cms-preview-text-hero',
    });
});
