/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/text-three-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/text-three-column',
        name: 'text-three-column',
        component: 'sw-cms-block-text-three-column',
        preview: 'sw-cms-preview-text-three-column',
    });
});
