/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/image-text-bubble', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/image-text-bubble',
        name: 'image-text-bubble',
        component: 'sw-cms-block-image-text-bubble',
        preview: 'sw-cms-preview-image-text-bubble',
    });
});
