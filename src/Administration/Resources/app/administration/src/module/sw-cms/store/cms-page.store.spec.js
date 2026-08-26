/**
 * @sw-package discovery
 */

describe('src/module/sw-cms/store/cms-page.store.ts', () => {
    it('should register a store', () => {
        expect(Shopware.Store.get('cmsPage')).toBeDefined();
    });

    it('should set the default state', () => {
        const cmsPageState = Shopware.Store.get('cmsPage');

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

    describe('updateElementConfig', () => {
        const slot = () => ({
            id: 'slot-1',
            config: {
                media: {
                    source: 'static',
                    value: null,
                },
            },
        });

        function setCurrentPage(elementSlot) {
            Shopware.Store.get('cmsPage').currentPage = {
                sections: [
                    { blocks: [] },
                    { blocks: [{ slots: [elementSlot] }] },
                ],
            };
        }

        afterEach(() => {
            Shopware.Store.get('cmsPage').removeCurrentPage();
        });

        it('should write a config value of an element of the current page', () => {
            const elementSlot = slot();
            setCurrentPage(elementSlot);

            Shopware.Store.get('cmsPage').updateElementConfig('slot-1', 'media.value', 'media-id');

            expect(elementSlot.config.media.value).toBe('media-id');
        });

        it('should create a config path the element does not carry yet', () => {
            const elementSlot = slot();
            setCurrentPage(elementSlot);

            Shopware.Store.get('cmsPage').updateElementConfig('slot-1', 'headline', { source: 'static', value: 'Hi' });

            expect(elementSlot.config.headline).toEqual({ source: 'static', value: 'Hi' });
        });

        it('should ignore an element that is not part of the current page', () => {
            const elementSlot = slot();
            setCurrentPage(elementSlot);

            Shopware.Store.get('cmsPage').updateElementConfig('other-slot', 'media.value', 'media-id');

            expect(elementSlot.config.media.value).toBeNull();
        });

        it('should ignore a write while no page is open', () => {
            expect(() => {
                Shopware.Store.get('cmsPage').updateElementConfig('slot-1', 'media.value', 'media-id');
            }).not.toThrow();
        });
    });
});
