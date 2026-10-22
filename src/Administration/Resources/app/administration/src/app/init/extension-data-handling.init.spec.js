/**
 * @sw-package framework
 */
import initializeExtensionDataLoader from 'src/app/init/extension-data-handling.init';
import { send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import Criteria from 'src/core/data/criteria.data';

describe('src/app/init/extension-data-handling.init.ts', () => {
    initializeExtensionDataLoader();
    let searchResult = { searchFoo: 'bar' };
    let getResult = { getFoo: 'bar' };
    // save/saveAll/delete resolve with the raw request response, whose config carries the admin auth token
    let saveResult = { saveFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
    let cloneResult = { cloneFoo: 'bar' };
    let hasChangesResult = true;
    let saveAllResult = { saveAllFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
    let deleteResult = { deleteFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
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
    // get and clone resolve asynchronously like the real repository
    const getMockMethod = jest.fn((id, context) => {
        return Promise.resolve({
            ...getResult,
            context,
        });
    });
    const saveMockMethod = jest.fn(() => saveResult);
    const cloneMockMethod = jest.fn((id, behaviour, context) => {
        return Promise.resolve({
            ...cloneResult,
            context,
        });
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

    beforeEach(() => {
        // Setup the spy before each test since restoreMocks: true in jest.config.js
        // automatically restores mocks after each test
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
        saveResult = { saveFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
        cloneResult = { cloneFoo: 'bar' };
        hasChangesResult = true;
        saveAllResult = { saveAllFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
        deleteResult = { deleteFoo: 'bar', config: { headers: { Authorization: 'Bearer leaked-admin-token' } } };
        createResult = { createFoo: 'bar' };

        // create mock extension
        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('extensions').addExtension({
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
        expect(searchMockMethod).toHaveBeenCalledWith(
            searchCriteria,
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
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
        expect(getMockMethod).toHaveBeenCalledWith(
            'my-awesome-id',
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
            getCriteria,
        );
        expect(result).toEqual({
            ...getResult,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });
    });

    it('should not forward an extension criteria that looks like cache options to repositoryGet', async () => {
        const maliciousCriteria = { cacheKey: ['extension-cache-key'] };

        await send('repositoryGet', {
            entityName: 'product',
            id: 'my-awesome-id',
            criteria: maliciousCriteria,
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        const forwardedCriteria = getMockMethod.mock.calls[0][2];
        expect(forwardedCriteria).toBeInstanceOf(Criteria);
        expect(forwardedCriteria).not.toHaveProperty('cacheKey');
    });

    it('should forward a valid Criteria instance unchanged to repositoryGet', async () => {
        const validCriteria = new Criteria();
        validCriteria.addAssociation('categories');

        await send('repositoryGet', {
            entityName: 'product',
            id: 'my-awesome-id',
            criteria: validCriteria,
            context: {},
        });

        const forwardedCriteria = getMockMethod.mock.calls[0][2];
        // the guard must keep a real Criteria and not replace it with an empty one
        expect(forwardedCriteria).toBeInstanceOf(Criteria);
        expect(forwardedCriteria.hasAssociation('categories')).toBe(true);
    });

    it('should drop a cacheKey from a deserialized criteria', async () => {
        await send('repositoryGet', {
            entityName: 'product',
            id: 'my-awesome-id',
            criteria: {
                __type__: '__Criteria__',
                data: { ...new Criteria().getCriteriaData(), cacheKey: ['extension-cache-key'] },
            },
            context: {},
        });

        const forwardedCriteria = getMockMethod.mock.calls[0][2];
        expect(forwardedCriteria).toBeInstanceOf(Criteria);
        expect(forwardedCriteria).not.toHaveProperty('cacheKey');
    });

    it('should filter nested contexts of the resolved repositoryGet result', async () => {
        getMockMethod.mockImplementationOnce((id, context) => {
            return Promise.resolve({
                ...getResult,
                categories: {
                    context,
                },
            });
        });

        const result = await send('repositoryGet', {
            entityName: 'product',
            id: 'my-awesome-id',
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(result.categories.context).toEqual({
            languageId: 'my-awesome-language-id',
        });
    });

    it('should not pass a cacheKey supplied by the extension to the repository', async () => {
        let searchContext;
        searchMockMethod.mockImplementationOnce((criteria, context) => {
            // copy the context, because filterContext mutates the returned result afterwards
            searchContext = { ...context };

            return Promise.resolve({
                ...searchResult,
                context,
            });
        });

        await send('repositorySearch', {
            entityName: 'product',
            criteria: new Criteria(),
            context: {
                cacheKey: ['extension-cache-key'],
                languageId: 'my-awesome-language-id',
            },
        });

        expect(searchContext).not.toHaveProperty('cacheKey');
        expect(searchContext.languageId).toBe('my-awesome-language-id');
        expect(searchContext.authToken).toEqual(Shopware.Context.api.authToken);
    });

    it('should never return the auth token, even if the extension requests it', async () => {
        searchMockMethod.mockImplementationOnce(() => {
            return Promise.resolve({
                ...searchResult,
                context: { ...Shopware.Context.api },
            });
        });

        const result = await send('repositorySearch', {
            entityName: 'product',
            criteria: new Criteria(),
            context: {
                authToken: true,
                languageId: 'my-awesome-language-id',
            },
        });

        expect(result.context).not.toHaveProperty('authToken');
        expect(result.context).toHaveProperty('languageId');
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
        expect(saveMockMethod).toHaveBeenCalledWith(
            {
                name: 'my-awesome-product',
            },
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
        // The repository response carries the admin auth token, so the handler must not return it
        expect(result).toBeNull();
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
            entities: [
                {
                    my: 'entity',
                },
            ],
            context: {
                languageId: 'my-awesome-language-id',
            },
        });

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product');
        expect(saveAllMockMethod).toHaveBeenCalledWith(
            [
                {
                    my: 'entity',
                },
            ],
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
        // The repository response carries the admin auth token, so the handler must not return it
        expect(result).toBeNull();
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
        expect(deleteMockMethod).toHaveBeenCalledWith(
            'my-awesome-id',
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
        // The repository response carries the admin auth token, so the handler must not return it
        expect(result).toBeNull();
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
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
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
        Shopware.Store.get('extensions').extensionsState = {};

        const searchCriteria = new Criteria();
        searchCriteria.setPage(1);
        searchCriteria.setLimit(25);

        let result;
        try {
            result = await send('repositorySearch', {
                entityName: 'product',
                criteria: searchCriteria,
                context: {
                    languageId: 'my-awesome-language-id',
                },
            });
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
        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('extensions').addExtension({
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

        expect(Shopware.Service('repositoryFactory').create).toHaveBeenCalledWith('product', '', {
            'sw-app-integration-id': 'my-awesome-integration-id',
        });
        expect(searchMockMethod).toHaveBeenCalledWith(
            searchCriteria,
            expect.objectContaining({
                languageId: 'my-awesome-language-id',
            }),
        );
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
                result = await send(method, {
                    entityName: 'not-existing-entity',
                    criteria: searchCriteria,
                    context: {
                        languageId: 'my-awesome-language-id',
                    },
                });
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
            result = await send('repositorySearch', {
                entityName: 'product',
                criteria: searchCriteria,
                context: {
                    languageId: 'my-awesome-language-id',
                    shouldFail: true,
                },
            });
        } catch (e) {
            result = e;
        }

        expect(result).toBeInstanceOf(Error);
        expect(result.message).toBe('Some search failure');
    });
});
