import initializeLocaleService from 'src/app/init/locale.init';

describe('src/app/init/locale.init.ts', () => {
    beforeAll(() => {
        initializeLocaleService();
    });

    it('should register the locale factory with correct snippet languages', () => {
        expect(Shopware.Application.getContainer('factory').locale).toEqual(expect.objectContaining({
            getLocaleByName: expect.any(Function),
            getLocaleRegistry: expect.any(Function),
            register: expect.any(Function),
            extend: expect.any(Function),
            getBrowserLanguage: expect.any(Function),
            getBrowserLanguages: expect.any(Function),
            getLastKnownLocale: expect.any(Function),
            storeCurrentLocale: expect.any(Function),
        }));
    });
});
