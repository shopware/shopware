/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/image-text-row', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/image-text-row',
        name: 'image-text-row',
        component: 'sw-cms-block-image-text-row',
        preview: 'sw-cms-preview-image-text-row',
    });
});
