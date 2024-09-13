/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-three-cover', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-three-cover',
        name: 'image-three-cover',
        component: 'sw-cms-block-image-three-cover',
        preview: 'sw-cms-preview-image-three-cover',
    });
});
