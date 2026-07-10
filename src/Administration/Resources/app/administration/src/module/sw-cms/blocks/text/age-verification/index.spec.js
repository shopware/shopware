/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/text/age-verification', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/text/age-verification',
        name: 'age-verification',
        component: 'sw-cms-block-age-verification',
        preview: 'sw-cms-preview-age-verification',
    });
});
