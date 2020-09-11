// import * as mapErrors from 'src/app/service/map-errors.service';
import * as mapErrors from 'src/app/service/map-errors.service';

describe('app/service/map-errors.service.js', () => {
    Shopware.Utils.debug.warn = jest.fn();

    beforeEach(() => {
        Shopware.Utils.debug.warn.mockClear();
    });

    it('all: should be an object', async () => {
        const type = typeof mapErrors;
        expect(type).toEqual('object');
    });

    it('all: should contain mapApiErrors function', async () => {
        expect(mapErrors).toHaveProperty('mapApiErrors');
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

    it('mapApiErrors: should reference the mapApiErrors function to mapPropertyErrors', async () => {
        const spy = jest.spyOn(mapErrors, 'mapPropertyErrors');

        mapErrors.mapApiErrors('testEntity', []);

        expect(spy).toHaveBeenCalledTimes(1);

        spy.mockRestore();
    });

    it('mapApiErrors: should create a console warning for deprecation', async () => {
        mapErrors.mapApiErrors('testEntity', []);

        expect(Shopware.Utils.debug.warn).toHaveBeenCalledWith(
            'mapApiErrors',
            'The componentHelper "mapApiErrors" is deprecated and will be removed in 6.4.0 - use "mapPropertyErrors" instead'
        );
    });

    it('mapPropertyErrors: should return an object with properties in camel case', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);

        expect(computedValues).toHaveProperty('testEntityNameError');
        expect(computedValues).toHaveProperty('testEntityIdError');
    });

    it('mapPropertyErrors: should return the getterPropertyError function', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);

        expect(computedValues.testEntityNameError.name).toEqual('getterPropertyError');
        expect(computedValues.testEntityIdError.name).toEqual('getterPropertyError');
    });

    it('mapPropertyErrors: the getterPropertyError should get the entity name from the vue instance', async () => {
        const spyGetEntityName = jest.fn(() => 'test_entity');

        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);
        const computedValueTestEntityNameError = computedValues.testEntityNameError.bind({
            testEntity: {
                getEntityName: spyGetEntityName
            }
        });

        expect(spyGetEntityName).not.toHaveBeenCalled();

        computedValueTestEntityNameError();

        expect(spyGetEntityName).toHaveBeenCalled();
    });

    it('mapPropertyErrors: the getterPropertyError should return null when entity is not in the vue instance', async () => {
        const computedValues = mapErrors.mapPropertyErrors('testEntity', ['name', 'id']);
        const computedValueTestEntityNameError = computedValues.testEntityNameError.bind({});

        expect(computedValueTestEntityNameError()).toEqual(null);
    });

    it('mapCollectionPropertyErrors: should return an object with properties in camel case', async () => {
        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);

        expect(computedValues).toHaveProperty('testEntityCollectionNameError');
        expect(computedValues).toHaveProperty('testEntityCollectionIdError');
    });

    it('mapCollectionPropertyErrors: should return the getterCollectionError function', async () => {
        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);

        expect(computedValues.testEntityCollectionNameError.name).toEqual('getterCollectionError');
        expect(computedValues.testEntityCollectionIdError.name).toEqual('getterCollectionError');
    });

    // eslint-disable-next-line max-len
    it('mapCollectionPropertyErrors: the getterCollectionError should get the entity name from the vue instance for each entity', async () => {
        const spyGetEntityNameOne = jest.fn(() => 'test_entity');
        const spyGetEntityNameTwo = jest.fn(() => 'test_entity');

        const computedValues = mapErrors.mapCollectionPropertyErrors('testEntityCollection', ['name', 'id']);
        const computedValueTestEntityCollectionNameError = computedValues.testEntityCollectionNameError.bind({
            testEntityCollection: [
                {
                    getEntityName: spyGetEntityNameOne
                },
                {
                    getEntityName: spyGetEntityNameTwo
                }
            ]
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

        expect(computedValueTestEntityNameError()).toEqual(null);
    });

    it('mapPageErrors: it should return an object', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({});

        expect(typeof mapPageErrors).toEqual('object');
    });

    it('mapPageErrors: the object should contain functions for each configuration', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({
            routeOne: {
                product: {},
                manufcaturer: {}
            }
        });

        expect(typeof mapPageErrors.routeOneError).toEqual('function');
        expect(mapPageErrors.routeOneError.name).toEqual('getterPropertyError');
    });

    it('mapPageErrors: it should check if the entity has an error', async () => {
        const mapPageErrors = mapErrors.mapPageErrors({
            routeOne: {
                product: {},
                manufcaturer: {}
            }
        });

        const errorExists = mapPageErrors.routeOneError();
        expect(errorExists).toBeFalsy();
    });
});
