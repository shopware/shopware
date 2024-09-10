/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-simple-grid', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-simple-grid',
        name: 'image-simple-grid',
        component: 'sw-cms-block-image-simple-grid',
        preview: 'sw-cms-preview-image-simple-grid',
    });
});
