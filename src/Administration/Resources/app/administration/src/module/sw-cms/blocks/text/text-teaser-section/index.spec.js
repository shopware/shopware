/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text-teaser-section', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text-teaser-section',
        name: 'text-teaser-section',
        component: 'sw-cms-block-text-teaser-section',
        preview: 'sw-cms-preview-text-teaser-section',
    });
});
