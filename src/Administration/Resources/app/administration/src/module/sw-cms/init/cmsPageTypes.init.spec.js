import CmsPageTypeService from '../service/cms-page-type.service';
import initCmsPageTypes from './cmsPageTypes.init';

describe('module/sw-cms/service/cms-page-type.service.ts', () => {
    let cmsPageTypeService;

    beforeAll(() => {
        Shopware.Service().register('cmsPageTypeService', () => {
            return new CmsPageTypeService();
        });

        cmsPageTypeService = Shopware.Service().get('cmsPageTypeService');
    });

    it('should call pageType.register() for each default type', () => {
        const types = cmsPageTypeService.getTypes();
        expect(types).toHaveLength(0);

        initCmsPageTypes();

        expect(types).toHaveLength(4);
    });
});
