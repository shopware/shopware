/**
 * @package admin
 */

// import * as mapErrors from 'src/app/service/map-errors.service';
import * as mapErrors from 'src/app/service/map-errors.service';
import ShopwareError from 'src/core/data/ShopwareError';

describe('app/service/map-errors.service.js', () => {
    Shopware.Utils.debug.warn = jest.fn();

    beforeEach(async () => {
        Shopware.Utils.debug.warn.mockClear();
    });

    it('all: should be an object', async () => {
        const type = typeof mapErrors;
        expect(type).toBe('object');
    });

    it('all: should contain mapPropertyErrors function', async () => {
        expect(mapErrors).toHaveProperty('mapPropertyErrors');
    });

    it('all: should contain mapCollectionPropertyErrors function', async () => {
        expect(mapErrors).toHaveProperty('mapCollectionPropertyErrors');
    });

    it('all: should contain mapPageErrors function', async () => {
        expect(mapErrors).toHaveProperty('mapPageErrors');
    });

    it('all: should contain mapSystemConfigErrors function', () => {
        expect(mapErrors).toHaveProperty('mapSystemConfigErrors');
    });

    it('mapPropertyErrors: should return an object with properties in camel case', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);

        expect(computedValues).toHaveProperty('testEntityNameError');
        expect(computedValues).toHaveProperty('testEntityIdError');
    });

    it('mapPropertyErrors: should return the getterPropertyError function', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);

        expect(computedValues.testEntityNameError.name).toBe('getterPropertyError');
        expect(computedValues.testEntityIdError.name).toBe('getterPropertyError');
    });

    it('mapPropertyErrors: the getterPropertyError should get the entity name from the vue instance', async () => {
        const spyGetEntityName = jest.fn(() => 'test_entity');

        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);
        const computedValueTestEntityNameError = computedValues.testEntityNameError.bind({
            testEntity: {
                getEntityName: spyGetEntityName,
            },
        });

        expect(spyGetEntityName).not.toHaveBeenCalled();

        computedValueTestEntityNameError();

        expect(spyGetEntityName).toHaveBeenCalled();
    });

    it('mapPropertyErrors: the getterPropertyError should return null when entity is not in the vue instance', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);
        const computedValueTestEntityNameError = computedValues.testEntityNameError.bind({});

        expect(computedValueTestEntityNameError()).toBeNull();
    });

    it('mapCollectionPropertyErrors: should return an object with properties in camel case', async () => {
        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);

        expect(computedValues).toHaveProperty('testEntityCollectionNameError');
        expect(computedValues).toHaveProperty('testEntityCollectionIdError');
    });

    it('mapCollectionPropertyErrors: should return the getterCollectionError function', async () => {
        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);

        expect(computedValues.testEntityCollectionNameError.name).toBe('getterCollectionError');
        expect(computedValues.testEntityCollectionIdError.name).toBe('getterCollectionError');
    });

    // eslint-disable-next-line max-len
    it('mapCollectionPropertyErrors: the getterCollectionError should get the entity name from the vue instance for each entity', async () => {
        const spyGetEntityNameOne = jest.fn(() => 'test_entity');
        const spyGetEntityNameTwo = jest.fn(() => 'test_entity');

        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);
        const computedValueTestEntityCollectionNameError = computedValues.testEntityCollectionNameError.bind({
            testEntityCollection: [
                {
                    getEntityName: spyGetEntityNameOne,
                },
                {
                    getEntityName: spyGetEntityNameTwo,
                },
            ],
        });

        expect(spyGetEntityNameOne).not.toHaveBeenCalled();
        expect(spyGetEntityNameTwo).not.toHaveBeenCalled();

        computedValueTestEntityCollectionNameError();

        expect(spyGetEntityNameOne).toHaveBeenCalled();
        expect(spyGetEntityNameTwo).toHaveBeenCalled();
    });

    it('mapCollectionPropertyErrors: the getterCollectionError should return null ' +
        'when entityCollection is not in the vue instance', async () => {
        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);
        const computedValueTestEntityNameError = computedValues.testEntityCollectionNameError.bind({});

        expect(computedValueTestEntityNameError()).toBeNull();
    });

    it('mapPageErrors: it should return an object', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({});

        expect(typeof mapPageErrors).toBe('object');
    });

    it('mapPageErrors: the object should contain functions for each configuration', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({
            routeOne: {
                product: {},
                manufcaturer: {},
            },
        });

        expect(typeof mapPageErrors.routeOneError).toBe('function');
        expect(mapPageErrors.routeOneError.name).toBe('getterPropertyError');
    });

    it('mapPageErrors: it should check if the entity has an error', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({
            routeOne: {
                product: {},
                manufcaturer: {},
            },
        });

        const errorExists = mapPageErrors.routeOneError();
        expect(errorExists).toBeFalsy();
    });

    it('mapSystemConfigErrors: it should return null', () => {
        const result = mapErrors.mapSystemConfigErrors('testEntityName', 'testSaleChannelId');

        expect(result).toBeNull();
    });

    it('mapSystemConfigErrors: it should return an object', () => {
        Shopware.State.dispatch('error/addApiError', {
            expression: 'SYSTEM_CONFIG.testSaleChannelId.dummyKey',
            error: new ShopwareError({ code: 'dummyCode' }),
        });

        const result = mapErrors.mapSystemConfigErrors('SYSTEM_CONFIG', 'testSaleChannelId', 'dummyKey');

        expect(result).toBeInstanceOf(ShopwareError);
    });
});
