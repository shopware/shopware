/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-highlight-row', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-highlight-row',
        name: 'image-highlight-row',
        component: 'sw-cms-block-image-highlight-row',
        preview: 'sw-cms-preview-image-highlight-row',
    });
});
