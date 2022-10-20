import LocaleHelperService from 'src/app/service/locale-helper.service';

describe('app/service/locale-helper.service.js', () => {
    let localeHelperService;

    beforeEach(() => {
        localeHelperService = new LocaleHelperService({
            Shopware: { Context: { api: {} }, State: { dispatch: () => Promise.resolve() } },
            localeRepository: { get: () => Promise.resolve({ code: 'abc123def456' }) },
            snippetService: { getSnippets: () => Promise.resolve() },
            localeFactory: {}
        });
    });

    it('should be an class', async () => {
        const type = typeof LocaleHelperService;
        expect(type).toEqual('function');
    });

    it('setLocaleWithId should call setLocaleWithCode', async () => {
        localeHelperService.setLocaleWithCode = jest.fn();

        await localeHelperService.setLocaleWithId('12345678');

        expect(localeHelperService.setLocaleWithCode).toHaveBeenCalledWith('abc123def456');
    });

    it('setLocaleWithId convert the locale id to code', async () => {
        localeHelperService.setLocaleWithCode = jest.fn();
        localeHelperService._localeRepository.get = async () => ({ code: 'converted locale' });

        await localeHelperService.setLocaleWithId('12345678');

        expect(localeHelperService.setLocaleWithCode).toHaveBeenCalledWith('converted locale');
    });

    it('setLocaleWithId get the locale id from the localeRepository', async () => {
        const shouldBeCalled = jest.fn();

        localeHelperService._localeRepository.get = async (value) => {
            shouldBeCalled(value);
            return { code: '' };
        };

        await localeHelperService.setLocaleWithId('12345678');
        expect(shouldBeCalled).toHaveBeenCalledWith('12345678');
    });

    it('setLocaleWithCode should call the snippet service with the code', async () => {
        localeHelperService._snippetService.getSnippets = jest.fn();

        await localeHelperService.setLocaleWithCode('testCode');

        expect(localeHelperService._snippetService.getSnippets).toHaveBeenCalledWith({}, 'testCode');
    });

    it('setLocaleWithCode should dispatch the admin locale', async () => {
        localeHelperService._Shopware.State.dispatch = jest.fn();

        await localeHelperService.setLocaleWithCode('testCode');

        expect(localeHelperService._Shopware.State.dispatch).toHaveBeenCalledWith('setAdminLocale', 'testCode');
    });
});
