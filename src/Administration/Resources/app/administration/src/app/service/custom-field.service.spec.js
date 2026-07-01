/**
 * @sw-package framework
 */

import createCustomFieldService from 'src/app/service/custom-field.service';
import CacheService from 'src/app/service/cache.service';

if (!Shopware.Service('cacheService')) {
    Shopware.Service().register('cacheService', () => new CacheService());
}

describe('src/app/service/custom-field.service.js', () => {
    let customFieldService;
    const expectedTypeConfigs = {
        number: {
            configRenderComponent: 'sw-custom-field-type-number',
            type: 'int',
            config: {
                componentName: 'sw-field',
                type: 'number',
                numberType: 'float',
            },
        },
    };

    beforeEach(() => {
        jest.restoreAllMocks();
        Shopware.Service('cacheService').clear();
        customFieldService = createCustomFieldService();
    });

    it('getTypeByName: get number type config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(customFieldService.getTypes().number);
    });

    it('getTypeByName: get unknown type config', async () => {
        expect(customFieldService.getTypeByName('unknownType')).toBeUndefined();
    });

    it('getTypeByName: checking expected config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(expectedTypeConfigs.number);
    });

    it('upsertType: insert config of new type', async () => {
        expect(customFieldService.getTypeByName('newType')).toBeUndefined();

        const newTypeConfig = {
            configRenderComponent: 'sw-custom-field-type-new-type',
            type: 'newType',
            config: {
                componentName: 'sw-field',
                type: 'newType',
            },
        };
        customFieldService.upsertType('newType', newTypeConfig);

        expect(customFieldService.getTypeByName('newType')).toEqual(newTypeConfig);
    });

    it('upsertType: upsert config', async () => {
        expect(customFieldService.getTypeByName('number')).toEqual(expectedTypeConfigs.number);

        const newConfig = {
            ...expectedTypeConfigs.number,
            type: 'float',
            config: {
                ...expectedTypeConfigs.number.config,
                numberType: 'float',
            },
        };

        customFieldService.upsertType('number', newConfig);

        expect(customFieldService.getTypeByName('number')).toEqual(newConfig);
    });

    it('reuses cached custom field sets per entity name and language', async () => {
        const productSets = [{ id: 'product-set', customFields: [{ id: 'field' }] }];
        const translatedProductSets = [{ id: 'product-set-language-2', customFields: [{ id: 'field' }] }];
        const customerSets = [{ id: 'customer-set', customFields: [{ id: 'field' }] }];
        const searchMock = jest
            .fn()
            .mockResolvedValueOnce(productSets)
            .mockResolvedValueOnce(translatedProductSets)
            .mockResolvedValueOnce(customerSets);

        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock });

        Shopware.Context.api.languageId = 'language-1';
        expect(await customFieldService.getCustomFieldSets('product')).toEqual(productSets);
        expect(await customFieldService.getCustomFieldSets('product')).toEqual(productSets);

        Shopware.Context.api.languageId = 'language-2';
        expect(await customFieldService.getCustomFieldSets('product')).toEqual(translatedProductSets);

        expect(await customFieldService.getCustomFieldSets('customer')).toEqual(customerSets);
        expect(searchMock).toHaveBeenCalledTimes(3);
    });
});
