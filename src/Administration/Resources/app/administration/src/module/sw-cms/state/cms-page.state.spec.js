/**
 * @package buyers-experience
 * @group disabledCompat
 */
import 'src/module/sw-cms/state/cms-page.state';

describe('src/module/sw-cms/state/cms-page.state.ts', () => {
    it('should register a store', () => {
        expect(Shopware.Store.get('cmsPageState')).toBeDefined();
    });

    it('should set the default state', () => {
        const cmsPageState = Shopware.Store.get('cmsPageState');

        expect(cmsPageState.currentPage).toBeNull();
        expect(cmsPageState.currentPageType).toBeNull();
        expect(cmsPageState.currentMappingEntity).toBeNull();
        expect(cmsPageState.currentMappingTypes).toStrictEqual({});
        expect(cmsPageState.currentDemoEntity).toBeNull();
        expect(cmsPageState.currentDemoProducts).toStrictEqual([]);
        expect(cmsPageState.pageEntityName).toBe('cms_page');
        expect(cmsPageState.defaultMediaFolderId).toBeNull();
        expect(cmsPageState.currentCmsDeviceView).toBe('desktop');
        expect(cmsPageState.selectedSection).toBeNull();
        expect(cmsPageState.selectedBlock).toBeNull();
        expect(cmsPageState.isSystemDefaultLanguage).toBe(true);
    });
});
