/**
 * @package admin
 */

import createCustomFieldService from 'src/app/service/custom-field.service';

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
});
