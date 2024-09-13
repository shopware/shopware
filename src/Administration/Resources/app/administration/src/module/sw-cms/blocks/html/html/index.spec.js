/**
 * @package buyers-experience
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/html/html', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/html/html',
        name: 'html',
        component: 'sw-cms-block-html',
        preview: 'sw-cms-preview-html',
    });
});
