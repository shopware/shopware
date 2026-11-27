/**
 * @sw-package framework
 */

import { flushPromises } from '@vue/test-utils';
import LanguageAutoFetchingService from 'src/app/service/language-auto-fetching.service';

describe('src/app/service/language-auto-fetching.service.js', () => {
    let repositoryGetMock;

    beforeEach(() => {
        // Reset the isInitialized flag by re-importing the module
        jest.resetModules();

        repositoryGetMock = jest.fn((id) =>
            Promise.resolve({
                id,
                name: `Language ${id}`,
                parentId: null,
            }),
        );

        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockImplementation(() => ({
            get: repositoryGetMock,
        }));

        Shopware.Store.get('context').api.languageId = 'initial-language-id';
        Shopware.Store.get('context').api.language = null;
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should load the language on initialization', async () => {
        LanguageAutoFetchingService();
        await flushPromises();

        expect(repositoryGetMock).toHaveBeenCalledWith(
            'initial-language-id',
            expect.objectContaining({ inheritance: true }),
            expect.anything(),
        );
        expect(repositoryGetMock.mock.calls[0][2].parse()).toMatchObject({ associations: { locale: {} } });

        expect(Shopware.Store.get('context').api.language).toEqual(
            expect.objectContaining({
                id: 'initial-language-id',
                name: 'Language initial-language-id',
            }),
        );
    });

    /**
     * The language entity was not reloaded after switching the content language.
     * @regression
     * @see https://github.com/shopware/shopware/issues/15084
     */
    it('should reload the language when languageId changes', async () => {
        LanguageAutoFetchingService();
        await flushPromises();

        // Reset mock to track new calls
        repositoryGetMock.mockClear();

        // Change the languageId
        Shopware.Store.get('context').api.languageId = 'new-language-id';
        await flushPromises();

        expect(repositoryGetMock).toHaveBeenCalledWith(
            'new-language-id',
            expect.objectContaining({ inheritance: true }),
            expect.anything(),
        );

        expect(Shopware.Store.get('context').api.language).toEqual(
            expect.objectContaining({
                id: 'new-language-id',
                name: 'Language new-language-id',
            }),
        );
    });

    it('ignores a language response that arrives after the language was switched again', async () => {
        LanguageAutoFetchingService();
        await flushPromises();

        let resolveStaleLanguage;
        repositoryGetMock.mockImplementationOnce(
            (id) =>
                new Promise((resolve) => {
                    resolveStaleLanguage = () => resolve({ id, name: `Language ${id}`, parentId: null });
                }),
        );

        Shopware.Store.get('context').api.languageId = 'stale-language-id';
        await flushPromises();
        Shopware.Store.get('context').api.languageId = 'current-language-id';
        await flushPromises();
        resolveStaleLanguage();
        await flushPromises();

        expect(Shopware.Store.get('context').api.language).toEqual(expect.objectContaining({ id: 'current-language-id' }));
    });
});
