/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';
import { IMAGE_DEFAULT_CONFIG } from 'src/module/sw-cms/elements/image/config.constant';

describe('src/module/sw-cms/elements/image', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/image',
        name: 'image',
        component: 'sw-cms-el-image',
        config: 'sw-cms-el-config-image',
        preview: 'sw-cms-el-preview-image',
    });

    it('should not ship a default min-height so none is sent through the API in non-cover modes', () => {
        expect(IMAGE_DEFAULT_CONFIG.displayMode.value).toBe('standard');
        expect(IMAGE_DEFAULT_CONFIG.minHeight.value).toBe('');
    });
});
