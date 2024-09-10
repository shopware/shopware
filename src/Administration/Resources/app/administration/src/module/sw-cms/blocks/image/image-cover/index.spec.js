/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-cover', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-cover',
        name: 'image-cover',
        component: 'sw-cms-block-image-cover',
        preview: 'sw-cms-preview-image-cover',
    });
});
