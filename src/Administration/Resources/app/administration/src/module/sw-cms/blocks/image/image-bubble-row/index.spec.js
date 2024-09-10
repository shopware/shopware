/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/image/image-bubble-row', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/image/image-bubble-row',
        name: 'image-bubble-row',
        component: 'sw-cms-block-image-bubble-row',
        preview: 'sw-cms-preview-image-bubble-row',
    });
});
