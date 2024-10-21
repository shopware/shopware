/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text-teaser', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text-teaser',
        name: 'text-teaser',
        component: 'sw-cms-block-text-teaser',
        preview: 'sw-cms-preview-text-teaser',
    });
});
