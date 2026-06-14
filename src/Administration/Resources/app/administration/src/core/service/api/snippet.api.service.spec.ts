/**
 * @sw-package discovery
 */

import SnippetApiService from 'src/core/service/api/snippet.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import LocaleFactory from 'src/core/factory/locale.factory';
import MockAdapter from 'axios-mock-adapter';
import type { AxiosInstance } from 'axios';

function createSnippetApiService() {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const snippetApiService = new SnippetApiService(client, loginService);
    return { snippetApiService, clientMock };
}

describe('core/service/api/snippet.api.service.ts', () => {
    beforeEach(() => {
        // Clear the locale registry before each test
        const registry = LocaleFactory.getLocaleRegistry();
        registry.clear();

        // Reset Shopware.Snippet mock
        Object.defineProperty(Shopware, 'Snippet', {
            value: undefined,
            writable: true,
            configurable: true,
        });
    });

    it('should be registered correctly', () => {
        const { snippetApiService } = createSnippetApiService();

        expect(snippetApiService).toBeInstanceOf(SnippetApiService);
        expect(snippetApiService.name).toBe('snippetService');
    });

    describe('getSnippets', () => {
        it('should load snippets and add them to locale registry', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            // Register a locale first
            LocaleFactory.register('en-GB', {
                existing: 'value',
            });

            // Verify initial state before loading snippets
            const registryBefore = LocaleFactory.getLocaleRegistry();
            const messagesBefore = registryBefore.get('en-GB');
            expect(messagesBefore).toEqual({
                existing: 'value',
            });
            expect(messagesBefore).not.toHaveProperty('loaded');

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    loaded: {
                        snippet: 'Loaded snippet',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'en-GB');

            const registry = LocaleFactory.getLocaleRegistry();
            const messages = registry.get('en-GB');

            expect(messages).toEqual({
                existing: 'value',
                loaded: {
                    snippet: 'Loaded snippet',
                },
            });
        });

        it('should register new locale if it does not exist', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            // Verify de-DE locale does not exist before getSnippets
            const registryBefore = LocaleFactory.getLocaleRegistry();
            expect(registryBefore.has('de-DE')).toBe(false);

            clientMock.onGet('/_admin/snippets?locale=de-DE').reply(200, {
                'de-DE': {
                    test: {
                        key: 'Test Wert',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'de-DE');

            // Verify de-DE locale exists after getSnippets
            const registry = LocaleFactory.getLocaleRegistry();
            expect(registry.has('de-DE')).toBe(true);

            const messages = registry.get('de-DE');
            expect(messages).toEqual({
                test: {
                    key: 'Test Wert',
                },
            });
        });

        it('should call setLocaleMessage when registering new locale with Shopware.Snippet instantiated', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            const setLocaleMessageMock = jest.fn();
            Object.defineProperty(Shopware, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            // Verify de-DE locale does not exist before getSnippets
            const registryBefore = LocaleFactory.getLocaleRegistry();
            expect(registryBefore.has('de-DE')).toBe(false);

            clientMock.onGet('/_admin/snippets?locale=de-DE').reply(200, {
                'de-DE': {
                    test: {
                        key: 'Test Wert',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'de-DE');

            // Should be called 2 times: service calls it twice
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(2);

            // Verify empty message is called before full messages (reactivity pattern)
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(1, 'de-DE', {});
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(2, 'de-DE', {
                test: {
                    key: 'Test Wert',
                },
            });
        });

        it('should not call setLocaleMessage when Shopware.Snippet is not instantiated', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {});

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    test: 'value',
                },
            });

            // Should not throw an error
            await expect(snippetApiService.getSnippets(LocaleFactory, 'en-GB')).resolves.not.toThrow();
        });

        it('should call setLocaleMessage when Shopware.Snippet is instantiated', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {
                existing: 'value',
            });

            const setLocaleMessageMock = jest.fn();
            Object.defineProperty(Shopware, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            // Verify mock hasn't been called yet
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(0);

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    loaded: {
                        snippet: 'Loaded snippet',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'en-GB');

            // Should be called 2 times: extend() calls it twice
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(2);

            // Verify empty message is called before full messages (reactivity pattern)
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(1, 'en-GB', {});
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(2, 'en-GB', {
                existing: 'value',
                loaded: {
                    snippet: 'Loaded snippet',
                },
            });
        });

        it('should set empty messages first to trigger reactivity update', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {
                base: 'value',
            });

            const callOrder: Array<{ locale: string; messages: object }> = [];
            const setLocaleMessageMock = jest.fn((locale: string, messages: object) => {
                callOrder.push({ locale, messages });
            });

            Object.defineProperty(Shopware, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            // Verify callOrder is empty before action
            expect(callOrder).toHaveLength(0);

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    api: {
                        snippet: 'API snippet',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'en-GB');

            // Verify the order of calls - should have 2 calls
            expect(callOrder).toHaveLength(2);

            // Verify that empty messages are set before full messages (reactivity pattern)
            expect(callOrder[0].messages).toEqual({});
            expect(callOrder[1].messages).toEqual({
                base: 'value',
                api: {
                    snippet: 'API snippet',
                },
            });
        });

        it('should handle multiple locales in response', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {});
            LocaleFactory.register('de-DE', {});

            // Verify both locales are registered but empty before action
            const registryBefore = LocaleFactory.getLocaleRegistry();
            expect(registryBefore.has('en-GB')).toBe(true);
            expect(registryBefore.has('de-DE')).toBe(true);
            expect(registryBefore.get('en-GB')).toEqual({});
            expect(registryBefore.get('de-DE')).toEqual({});

            const setLocaleMessageMock = jest.fn();
            Object.defineProperty(Shopware, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    english: 'English text',
                },
                'de-DE': {
                    german: 'Deutscher Text',
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'en-GB');

            // Should be called 4 times total (2 calls per locale)
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(4);

            // Verify both locales are updated with empty first, then full messages
            expect(setLocaleMessageMock).toHaveBeenCalledWith('en-GB', {});
            expect(setLocaleMessageMock).toHaveBeenCalledWith('en-GB', {
                english: 'English text',
            });
            expect(setLocaleMessageMock).toHaveBeenCalledWith('de-DE', {});
            expect(setLocaleMessageMock).toHaveBeenCalledWith('de-DE', {
                german: 'Deutscher Text',
            });
        });

        it('should use last known locale when no code is provided', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            // Mock getBrowserLanguage to return 'en-GB'
            LocaleFactory.register('en-GB', {});

            // Mock to capture the requested URL
            let requestedUrl = '';
            clientMock.onGet(/\/_admin\/snippets\?locale=.*/).reply((config) => {
                requestedUrl = config.url || '';
                return [
                    200,
                    {
                        'en-GB': {
                            test: 'value',
                        },
                    },
                ];
            });

            await snippetApiService.getSnippets(LocaleFactory);

            // Should use getLastKnownLocale which returns 'en-GB' in this case
            expect(requestedUrl).toMatch(/locale=en-GB/);
        });

        it('should handle optional chaining for setLocaleMessage', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {});

            // Mock Shopware.Snippet but without setLocaleMessage
            Object.defineProperty(Shopware, 'Snippet', {
                value: {},
                writable: true,
                configurable: true,
            });

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    test: 'value',
                },
            });

            // Should not throw an error
            await expect(snippetApiService.getSnippets(LocaleFactory, 'en-GB')).resolves.not.toThrow();
        });

        it('should properly merge snippets with existing locale messages', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            LocaleFactory.register('en-GB', {
                module: {
                    existing: 'existing value',
                },
            });

            // Verify initial state before loading snippets
            const registryBefore = LocaleFactory.getLocaleRegistry();
            const messagesBefore = registryBefore.get('en-GB');
            expect(messagesBefore).toEqual({
                module: {
                    existing: 'existing value',
                },
            });
            expect(messagesBefore).not.toHaveProperty('newModule');

            clientMock.onGet('/_admin/snippets?locale=en-GB').reply(200, {
                'en-GB': {
                    module: {
                        loaded: 'loaded value',
                    },
                    newModule: {
                        key: 'value',
                    },
                },
            });

            await snippetApiService.getSnippets(LocaleFactory, 'en-GB');

            const registry = LocaleFactory.getLocaleRegistry();
            const messages = registry.get('en-GB');

            expect(messages).toEqual({
                module: {
                    existing: 'existing value',
                    loaded: 'loaded value',
                },
                newModule: {
                    key: 'value',
                },
            });
        });
    });

    describe('getFilter', () => {
        it('should get filter correctly', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            clientMock.onGet('/_action/snippet/filter').reply(200, {
                total: 2,
                data: [
                    'filter1',
                    'filter2',
                ],
            });

            const result = await snippetApiService.getFilter();

            expect(result).toEqual({
                total: 2,
                data: [
                    'filter1',
                    'filter2',
                ],
            });
        });
    });

    describe('getLocales', () => {
        it('should get locales correctly', async () => {
            const { snippetApiService, clientMock } = createSnippetApiService();

            clientMock.onGet('/_admin/locales').reply(200, {
                'lang-id-1': 'en-GB',
                'lang-id-2': 'de-DE',
                'lang-id-3': 'fr-FR',
            });

            const result = await snippetApiService.getLocales();

            expect(result).toEqual({
                'lang-id-1': 'en-GB',
                'lang-id-2': 'de-DE',
                'lang-id-3': 'fr-FR',
            });
        });
    });
});
