/**
 * @package admin
 */
import initializeExtensionDataLoader from 'src/app/init/extension-data-handling.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import Criteria from 'src/core/data/criteria.data';

describe('src/app/init/extension-data-handling.init.ts', () => {
    initializeExtensionDataLoader();
    let searchResult = { searchFoo: 'bar' };
    let getResult = { getFoo: 'bar' };
    let saveResult = { saveFoo: 'bar' };
    let cloneResult = { cloneFoo: 'bar' };
    let hasChangesResult = true;
    let saveAllResult = { saveAllFoo: 'bar' };
    let deleteResult = { deleteFoo: 'bar' };
    let createResult = { createFoo: 'bar' };
    const searchMockMethod = jest.fn((criteria, context) => {
        if (context.shouldFail) {
            return Promise.reject(new Error('Some search failure'));
        }

        return {
            ...searchResult,
            context,
        };
    });
    const getMockMethod = jest.fn((criteria, context) => {
        return {
            ...getResult,
            context,
        };
    });
    const saveMockMethod = jest.fn(() => saveResult);
    const cloneMockMethod = jest.fn((criteria, behaviour, context) => {
        return {
            ...cloneResult,
            context,
        };
    });
    const hasChangesMockMethod = jest.fn(() => hasChangesResult);
    const saveAllMockMethod = jest.fn(() => saveAllResult);
    const deleteMockMethod = jest.fn(() => deleteResult);
    const createMockMethod = jest.fn((context) => {
        return {
            ...createResult,
            context,
        };
    });

    beforeAll(() => {
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation((entityName) => {
            if (entityName === 'not-existing-entity') {
                return undefined;
            }

            return {
                search: searchMockMethod,
                get: getMockMethod,
                save: saveMockMethod,
                clone: cloneMockMethod,
                hasChanges: hasChangesMockMethod,
                saveAll: saveAllMockMethod,
                delete: deleteMockMethod,
                create: createMockMethod,
            };
        });
    });

    beforeEach(() => {
        Shopware.Service('repositoryFactory').create.mockClear();
        searchMockMethod.mockClear();
        getMockMethod.mockClear();
        saveMockMethod.mockClear();
        cloneMockMethod.mockClear();
        hasChangesMockMethod.mockClear();
        saveAllMockMethod.mockClear();
        deleteMockMethod.mockClear();
        createMockMethod.mockClear();
        searchResult = { searchFoo: 'bar' };
        getResult = { getFoo: 'bar' };
        saveResult = { saveFoo: 'bar' };
        cloneResult = { cloneFoo: 'bar' };
        hasChangesResult = true;
        saveAllResult = { saveAllFoo: 'bar' };
        deleteResult = { deleteFoo: 'bar' };
        createResult = { createFoo: 'bar' };

        // create mock extension
        Shopware.State._store.state.extensions = {};
        Shopware.State.commit('extensions/addExtension', {
            name: 'MyAwesomeExtension',
            permissions: {},
            baseUrl: '',
            type: 'app',
            active: true,
        });
    });

    it('should handle repositorySearch', async () => {
        const searchCriteria = new Criteria();
        searchCriteria.setPage(1);
        searchCriteria.setLimit(25);

        const result = await send('repositorySearch', {
            entityName: 'product',
            criteria: searchCriteria,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(searchMockMethod).toHaveBeenCalledWith(searchCriteria, expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }));
        expect(result).toEqual({
            ...searchResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    it('should handle repositoryGet', async () => {
        const getCriteria = new Criteria();
        getCriteria.addAssociation('product_manufacturer');

        const result = await send('repositoryGet', {
            entityName: 'product',
            id: 'my-awesome-id',
            criteria: getCriteria,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(getMockMethod).toHaveBeenCalledWith('my-awesome-id', expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }), getCriteria);
        expect(result).toEqual({
            ...getResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    it('should handle repositorySave', async () => {
        const result = await send('repositorySave', {
            entityName: 'product',
            entity: {
                name: 'my-awesome-product',
            },
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(saveMockMethod).toHaveBeenCalledWith({
            name: 'my-awesome-product',
        }, expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }));
        expect(result).toEqual(saveResult);
    });

    it('should handle repositoryClone', async () => {
        const result = await send('repositoryClone', {
            entityName: 'product',
            behavior: 'my-awesome-behavior',
            entityId: 'my-awesome-id',
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(cloneMockMethod).toHaveBeenCalledWith(
            'my-awesome-id',
            'my-awesome-behavior',
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
        expect(result).toEqual({
            ...cloneResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    it('should handle repositoryHasChanges', async () => {
        const result = await send('repositoryHasChanges', {
            entityName: 'product',
            entity: {
                my: 'entity',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(hasChangesMockMethod).toHaveBeenCalledWith({
            my: 'entity',
        });
        expect(result).toEqual(hasChangesResult);
    });

    it('should handle repositorySaveAll', async () => {
        const result = await send('repositorySaveAll', {
            entityName: 'product',
            entities: [{
                my: 'entity',
            }],
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(saveAllMockMethod).toHaveBeenCalledWith([{
            my: 'entity',
        }], expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }));
        expect(result).toEqual(saveAllResult);
    });

    it('should handle repositoryDelete', async () => {
        const result = await send('repositoryDelete', {
            entityName: 'product',
            entityId: 'my-awesome-id',
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(deleteMockMethod).toHaveBeenCalledWith('my-awesome-id', expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }));
        expect(result).toEqual(deleteResult);
    });

    it('should handle repositoryCreate', async () => {
        const result = await send('repositoryCreate', {
            entityName: 'product',
            entityId: 'my-awesome-id',
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(createMockMethod).toHaveBeenCalledWith(
            expect.objectContaining(
                {
                    languageId: 'my-awesome-language-id',
                },
            ),
            'my-awesome-id',
        );

        expect(result).toEqual({
            ...createResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    it('should throw an error if no extension with the given event origin was found', async () => {
        // create mock extension with a different baseUrl
        Shopware.State._store.state.extensions = {};

        const searchCriteria = new Criteria();
        searchCriteria.setPage(1);
        searchCriteria.setLimit(25);

        let result;
        try {
            result = await send(
                'repositorySearch',
                {
                    entityName: 'product',
                    criteria: searchCriteria,
                    context: {
                        languageId: 'my-awesome-language-id',
                    },
                },
            );
        } catch (e) {
            result = e;
        }

        expect(result).toBeInstanceOf(Error);
        expect(result.message).toBe('Could not find a extension with the given event origin ""');
    });

    // eslint-disable-next-line jest/expect-expect
    it('should throw an error if no extension with the given name was found in the store', async () => {
        // Not testable because the error cannot be reproduced in jest without an iFrame
    });

    it('should handle repositorySearch with integrationId', async () => {
        Shopware.State._store.state.extensions = {};
        Shopware.State.commit('extensions/addExtension', {
            name: 'MyAwesomeExtension',
            permissions: {},
            baseUrl: '',
            type: 'app',
            active: true,
            integrationId: 'my-awesome-integration-id',
        });

        const searchCriteria = new Criteria();
        searchCriteria.setPage(1);
        searchCriteria.setLimit(25);

        const result = await send('repositorySearch', {
            entityName: 'product',
            criteria: searchCriteria,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith(
            'product',
            '',
            {
                'sw-app-integration-id': 'my-awesome-integration-id',
            },
        );
        expect(searchMockMethod).toHaveBeenCalledWith(searchCriteria, expect.objectContaining({
            languageId: 'my-awesome-language-id',
        }));
        expect(result).toEqual({
            ...searchResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    [
        'repositorySearch',
        'repositoryGet',
        'repositorySave',
        'repositoryClone',
        'repositoryHasChanges',
        'repositorySaveAll',
        'repositoryDelete',
        'repositoryCreate',
    ].forEach((method) => {
        it(`should prevent creation of repositories for undefined entities for ${method}`, async () => {
            const searchCriteria = new Criteria();
            searchCriteria.setPage(1);
            searchCriteria.setLimit(25);

            let result;
            try {
                result = await send(
                    method,
                    {
                        entityName: 'not-existing-entity',
                        criteria: searchCriteria,
                        context: {
                            languageId: 'my-awesome-language-id',
                        },
                    },
                );
            } catch (e) {
                result = e;
            }

            expect(result).toBeInstanceOf(Error);
            expect(result.message).toBe('Could not create repository for entity "not-existing-entity"');
        });
    });

    it('should return an error if the search return an error', async () => {
        const searchCriteria = new Criteria();
        searchCriteria.setPage(1);
        searchCriteria.setLimit(25);

        let result;
        try {
            result = await send(
                'repositorySearch',
                {
                    entityName: 'product',
                    criteria: searchCriteria,
                    context: {
                        languageId: 'my-awesome-language-id',
                        shouldFail: true,
                    },
                },
            );
        } catch (e) {
            result = e;
        }

        expect(result).toBeInstanceOf(Error);
        expect(result.message).toBe('Some search failure');
    });
});
