/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/image-text-cover', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/image-text-cover',
        name: 'image-text-cover',
        component: 'sw-cms-block-image-text-cover',
        preview: 'sw-cms-preview-image-text-cover',
    });
});
