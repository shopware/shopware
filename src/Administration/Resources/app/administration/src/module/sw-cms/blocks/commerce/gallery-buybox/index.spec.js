/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/gallery-buybox', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/gallery-buybox',
        name: 'gallery-buybox',
        component: 'sw-cms-block-gallery-buybox',
        preview: 'sw-cms-preview-gallery-buybox',
    });
});
