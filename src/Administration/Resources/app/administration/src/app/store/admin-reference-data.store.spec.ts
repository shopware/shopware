/**
 * @sw-package framework
 */
import 'src/app/store/admin-reference-data.store';

describe('admin-reference-data.store', () => {
    const store = Shopware.Store.get('adminReferenceData');

    beforeEach(() => {
        jest.restoreAllMocks();
        store.$reset();
        store.ttl = 100;
        Shopware.Context.app.systemCurrencyId = 'currency-id';
        Shopware.Context.api.languageId = 'language-id';
    });

    it('reuses the cached system currency while it is fresh', async () => {
        const currency = { id: 'currency-id', factor: 1 };
        const getMock = jest.fn().mockResolvedValue(currency);

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ get: getMock });

        expect(await store.loadSystemCurrency()).toStrictEqual(currency);
        expect(await store.loadSystemCurrency()).toStrictEqual(currency);
        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('reloads reference data after the ttl expires', async () => {
        const firstLanguages = [{ id: 'language-1' }];
        const secondLanguages = [{ id: 'language-2' }];
        const searchMock = jest.fn().mockResolvedValueOnce(firstLanguages).mockResolvedValueOnce(secondLanguages);

        jest.spyOn(Date, 'now').mockReturnValueOnce(1000).mockReturnValueOnce(1200).mockReturnValueOnce(1200);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock });

        expect(await store.loadActiveLanguages()).toStrictEqual(firstLanguages);
        expect(await store.loadActiveLanguages()).toStrictEqual(secondLanguages);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('reloads translated reference data when the api language changes', async () => {
        const firstTypes = [{ id: 'type-1', translated: { name: 'Storefront' } }];
        const secondTypes = [{ id: 'type-1', translated: { name: 'Headless' } }];
        const searchMock = jest.fn().mockResolvedValueOnce(firstTypes).mockResolvedValueOnce(secondTypes);

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock });

        expect(await store.loadSalesChannelTypes()).toStrictEqual(firstTypes);

        Shopware.Context.api.languageId = 'another-language-id';

        expect(await store.loadSalesChannelTypes()).toStrictEqual(secondTypes);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });
});
