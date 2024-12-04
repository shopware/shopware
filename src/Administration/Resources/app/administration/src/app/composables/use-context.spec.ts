import useContext from './use-context';

describe('use-context', () => {
    const mockSetItem = jest.fn();

    beforeAll(() => {
        Object.defineProperty(window, 'localStorage', {
            value: {
                setItem: mockSetItem,
            },
        });
    });

    it('has initial state', () => {
        const store = useContext();
        expect(store.app).toEqual(
            expect.objectContaining({
                config: {
                    adminWorker: null,
                    bundles: null,
                    version: null,
                    versionRevision: null,
                    inAppPurchases: {},
                },
                environment: null,
                fallbackLocale: null,
                features: null,
                firstRunWizard: null,
                systemCurrencyId: null,
                systemCurrencyISOCode: null,
                disableExtensions: false,
            }),
        );

        expect(store.api).toEqual(
            expect.objectContaining({
                apiPath: '/api',
                apiResourcePath: '/api/v3',
                assetsPath: '',
                authToken: {
                    access:
                        // eslint-disable-next-line max-len
                        'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImI0MTdkYjQ1MzMwNTY1MGIyY2QxMWVhYTBmZjRjNWJmZTVjZWYxYTI3NzBjY2JmY2M3MGY2Y2FiZDIzYWQyYmZiMzc1NTZhNDFlNGE3M2M5In0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6ImI0MTdkYjQ1MzMwNTY1MGIyY2QxMWVhYTBmZjRjNWJmZTVjZWYxYTI3NzBjY2JmY2M3MGY2Y2FiZDIzYWQyYmZiMzc1NTZhNDFlNGE3M2M5IiwiaWF0IjoxNjAyODM5OTgxLCJuYmYiOjE2MDI4Mzk5ODEsImV4cCI6MTYwMjg0MDU4MSwic3ViIjoiNzk5Y2NmNzY3MzZjNDkxYTgzNTA5MzA0Mjc3YzI3MTkiLCJzY29wZXMiOlsid3JpdGUiLCJhZG1pbiJdfQ.Df0EnZyZ-eY1iNCB-0x-0Ir8a8XW_HOdhq9HEcx7AbCEogHIFtU_0UPxTLX9_Wo3r-5C4FmbQrN31ReBWxkbEldMb3EU-UL4FIJA2gYhFWAXV2ZhaEJ5hRQ04n4gra0Os48vzYIEOq87_0lPPQqqVZLi68aHLVSF962VE1SkbofKqS2l2mDh9JJjnyhZavpkmpLhLkoWBBUWJS7G-EHo_-DttxPpA8W0Kgyg8Ch4Z2xqZ1r0zaB6hIS97-m8qLFHtjPhrbLW8NIMURIU3_brkkO2wFXrLKc0Y6MLJac8BVEe8VTEoEo8x8Ft2dCQU5aF2Aht3Y_55m1VjUMXBSb77A',
                    expiry: 1602840582,
                    refresh:
                        // eslint-disable-next-line max-len
                        'def5020065a671fb38ec810a50bb627db679fd9a046ca0187215d418986fce75d3b55f7e0588c33318f3f7a280edc1e82b764a6b1fb82275e457459e58fff73afaa2aac08acd23322d398a74babbd9e02c11228985a5f140742eaa2c30af55ae350aca32e898ca9a5955c0bf057dee2b39bb5134aa6176668744fe05d6dbc9a0294bf6fa4dd6b4b07ed5d235d89005eeffc0e69ddc072e2023e522a5fd699c3e68b1dcdcc9f60c63f62ff4ed1778abfb0f3b95c4b44ad92d885bf1dca115f086b1a2368e7326f467331b6a0e65049e790c4d3a35fc1d77dfbd91da74c4d7cc449604adecd41bd84596efa4651b75bef0eeba6aef0d33338be22bf4e816584aefce9588a85d1dafbe311e330835d54dc19f43baa7a7ad63ee9573c98444219d80266b52b6e840354596d369e8350f3df18dae21a9dc607dcf70d66ddf78652a0d4083b85a832cc808d61ad15c196e1579cdea3829a8b480572f7afd590cd18fe811b5596554a58c5800756fdb1c051a461e4d7cf7c94c552ccf79d7a1368dfe8e63f4402abbaa6cabbd92437cf3f78c302ea7492dd60f5cfd8f7b4e8aa714',
                },
                basePath: null,
                pathInfo: null,
                inheritance: false,
                installationPath: 'installationPath',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                language: null,
                apiVersion: null,
                liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                currencyId: null,
                versionId: null,
            }),
        );
    });

    it('adds a value to app context', () => {
        const store = useContext();
        store.addAppValue({ key: 'environment', value: 'development' });

        expect(store.app.environment).toBe('development');
    });

    it('adds a `true` value to app context', () => {
        const store = useContext();
        store.addAppValue({ key: 'disableExtensions', value: 'true' as unknown as boolean });

        expect(store.app.disableExtensions).toBe(true);
    });

    it('adds a `false` value to app context', () => {
        const store = useContext();
        store.addAppValue({ key: 'disableExtensions', value: 'false' as unknown as boolean });

        expect(store.app.disableExtensions).toBe(false);
    });

    it('adds a value to api context', () => {
        const store = useContext();
        store.addApiValue({ key: 'apiPath', value: '/test/test' });

        expect(store.api.apiPath).toBe('/test/test');
    });

    it('adds a value to app config context', () => {
        const store = useContext();
        store.addAppConfigValue({ key: 'version', value: 'test' });

        expect(store.app.config.version).toBe('test');
    });

    it('sets the Api Language Id to the store and to localStorage', () => {
        const store = useContext();
        store.setApiLanguageId('12345');

        expect(store.api.languageId).toBe('12345');
        expect(mockSetItem).toHaveBeenCalledWith('sw-admin-current-language', '12345');
    });

    it('resets the Api Language Id to the systemLanguageId', () => {
        const store = useContext();
        store.api.systemLanguageId = '54321';
        store.api.languageId = '12345';

        store.resetLanguageToDefault();

        expect(store.api.languageId).toBe('54321');
    });

    it('has a getter to know if is the System default language', () => {
        const store = useContext();
        store.api.systemLanguageId = '54321';
        store.api.languageId = '54321';

        expect(store.isSystemDefaultLanguage.value).toBe(true);

        store.api.languageId = '123345';
        expect(store.isSystemDefaultLanguage.value).toBe(false);
    });
});
