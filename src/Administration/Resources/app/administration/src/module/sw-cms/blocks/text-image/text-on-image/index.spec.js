/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text-image/text-on-image', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text-image/text-on-image',
        name: 'text-on-image',
        component: 'sw-cms-block-text-on-image',
        preview: 'sw-cms-preview-text-on-image',
    });
});
