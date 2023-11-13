import CmsPageTypeService from './cms-page-type.service';
import initCmsPageTypes from '../init/cmsPageTypes.init';

const expectedTypes = {
    page: {
        name: 'page',
        icon: 'regular-lightbulb',
        title: 'sw-cms.detail.label.pageType.page',
        class: ['sw-cms-create-wizard__page-type-page'],
        hideInList: false,
    },
    landingpage: {
        name: 'landingpage',
        icon: 'regular-dashboard',
        title: 'sw-cms.detail.label.pageType.landingpage',
        class: ['sw-cms-create-wizard__page-type-landingpage'],
        hideInList: false,
    },
    product_list: {
        name: 'product_list',
        icon: 'regular-shopping-basket',
        title: 'sw-cms.detail.label.pageType.productList',
        class: ['sw-cms-create-wizard__page-type-product-list'],
        hideInList: false,
    },
    product_detail: {
        name: 'product_detail',
        icon: 'regular-tag',
        title: 'sw-cms.detail.label.pageType.productDetail',
        class: ['sw-cms-create-wizard__page-type-product-detail'],
        hideInList: false,
    },
};

const mockType = {
    name: 'mock_type',
    icon: 'regular-tag',
    title: 'sw-cms.detail.label.pageType.mockType',
    class: ['sw-cms-create-wizard__page-type-mock-type'],
    hideInList: true,
};

describe('module/sw-cms/service/cms-page-type.service.ts', () => {
    let cmsPageTypeService;

    beforeAll(() => {
        Shopware.Service().register('cmsPageTypeService', () => {
            return new CmsPageTypeService();
        });

        cmsPageTypeService = Shopware.Service().get('cmsPageTypeService');

        initCmsPageTypes();
        cmsPageTypeService.register(mockType);
    });

    it('should return all types', () => {
        const types = cmsPageTypeService.getTypes();
        expect(types).toHaveLength(5);
    });

    it('should return all visible types', () => {
        const types = cmsPageTypeService.getVisibleTypes();
        expect(types).toHaveLength(4);
    });

    it('should return all type names', () => {
        const typeNames = cmsPageTypeService.getTypeNames();
        expect(typeNames).toHaveLength(5);
    });

    it('should throw an error, when an existing page type is added again', () => {
        expect(() => {
            cmsPageTypeService.register(mockType);
        }).toThrow(
            new Error("Can't register new Page Type with \"mock_type\" already in use."),
        );
    });

    Object.keys(expectedTypes).forEach((typeName) => {
        it(`should return a specific type by name (type: ${typeName})`, () => {
            const actualType = cmsPageTypeService.getType(typeName);
            expect(actualType).toStrictEqual(expectedTypes[typeName]);
        });
    });
});
