/**
 * @sw-package framework
 */
import initializeLocaleService from 'src/app/init/locale.init';
import initializeApiServices from 'src/app/init-pre/api-services.init';

describe('src/app/init/locale.init.ts', () => {
    beforeAll(() => {
        initializeApiServices();
        // initializeLocaleService();
    });

    it('should register the locale factory with correct snippet languages', async () => {
        await initializeLocaleService();

        expect(Shopware.Application.getContainer('factory').locale).toEqual(
            expect.objectContaining({
                getLocaleByName: expect.any(Function),
                getLocaleRegistry: expect.any(Function),
                register: expect.any(Function),
                extend: expect.any(Function),
                getBrowserLanguage: expect.any(Function),
                getBrowserLanguages: expect.any(Function),
                getLastKnownLocale: expect.any(Function),
                storeCurrentLocale: expect.any(Function),
            }),
        );
    });

    it('should register all locales for languages in the database', async () => {
        const expectedData = {
            'id1': 'en-GB',
            'id2': 'de-DE',
            'id3': 'fr-FR',
            'id4': 'jp-JP',
        };

        const mock = jest.fn();
        mock.mockReturnValue(expectedData);

        jest.spyOn(Shopware.Service('snippetService'), 'getSnippets').mockImplementation(() => {
            return {
                snippetService: jest.fn(),
                getSnippets: () => mock,
            };
        });

        const x = Shopware.Service('snippetService')//.getSnippets();


        expect(Shopware.Service('snippetService')).toBeDefined();

        // ToDo: Remove after Debug
        console.log('x:', x);
        // return;
        // Shopware.Service('snippetService').getSnippets = mock;

        await initializeLocaleService();

        const factoryContainer = Shopware.Application.getContainer('factory');
        const localeFactory = factoryContainer.locale;

        expect(localeFactory.getLocales()).toEqual(Array.from(expectedData), localeFactory.getLocales);
    });
});
