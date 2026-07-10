/**
 * @sw-package discovery
 */
import 'src/module/sw-cms/service/cms.service';
import './index';

describe('src/module/sw-cms/elements/age-verification/index.ts', () => {
    it('should register components correctly', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-cms-el-age-verification')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-cms-el-preview-age-verification')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-cms-el-config-age-verification')).toBe(true);
        expect(Object.keys(Shopware.Service('cmsService').getCmsElementRegistry())).toContain('age-verification');
    });

    it('should register sensible default config', () => {
        const element = Shopware.Service('cmsService').getCmsElementConfigByName('age-verification');

        expect(element.defaultConfig.minimumAge.value).toBe(18);
        expect(element.defaultConfig.cookieLifetime.value).toBe(30);
        expect(element.defaultConfig.title.value).toBe('');
    });
});
