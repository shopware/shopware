/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/image-text', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/image-text',
        name: 'image-text',
        component: 'sw-cms-block-image-text',
        preview: 'sw-cms-preview-image-text',
    });
});
