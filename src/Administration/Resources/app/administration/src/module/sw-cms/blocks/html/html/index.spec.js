/**
 * @package buyers-experience
 * @group disabledCompat
 */
import 'src/module/sw-cms/service/cms.service';
import './index';

describe('src/module/sw-cms/blocks/html/html/index.ts', () => {
    it('should register components correctly', () => {
        expect(Shopware.Component.getComponentRegistry().has('sw-cms-block-html')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-cms-preview-html')).toBe(true);
        expect(Object.keys(Shopware.Service('cmsService').getCmsBlockRegistry())).toContain('html');
    });
});
