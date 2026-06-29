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
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ get: getMock } as never);

        expect(await store.loadSystemCurrency()).toStrictEqual(currency);
        expect(await store.loadSystemCurrency()).toStrictEqual(currency);
        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('reloads reference data after the ttl expires', async () => {
        const firstLanguages = [{ id: 'language-1' }];
        const secondLanguages = [{ id: 'language-2' }];
        const searchMock = jest.fn().mockResolvedValueOnce(firstLanguages).mockResolvedValueOnce(secondLanguages);

        jest.spyOn(Date, 'now').mockReturnValueOnce(1000).mockReturnValueOnce(1200).mockReturnValueOnce(1200);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock } as never);

        expect(await store.loadActiveLanguages()).toStrictEqual(firstLanguages);
        expect(await store.loadActiveLanguages()).toStrictEqual(secondLanguages);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('reloads translated reference data when the api language changes', async () => {
        const firstTypes = [{ id: 'type-1', translated: { name: 'Storefront' } }];
        const secondTypes = [{ id: 'type-1', translated: { name: 'Headless' } }];
        const searchMock = jest.fn().mockResolvedValueOnce(firstTypes).mockResolvedValueOnce(secondTypes);

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock } as never);

        expect(await store.loadSalesChannelTypes()).toStrictEqual(firstTypes);

        Shopware.Context.api.languageId = 'another-language-id';

        expect(await store.loadSalesChannelTypes()).toStrictEqual(secondTypes);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('reuses cached taxes while they are fresh', async () => {
        const taxes = [{ id: 'tax-id', taxRate: 19 }];
        const searchMock = jest.fn().mockResolvedValue(taxes);

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ search: searchMock } as never);

        expect(await store.loadTaxes()).toStrictEqual(taxes);
        expect(await store.loadTaxes()).toStrictEqual(taxes);
        expect(searchMock).toHaveBeenCalledTimes(1);
    });

    it('reuses the cached default tax rate id while it is fresh', async () => {
        const getValuesMock = jest.fn().mockResolvedValue({
            'core.tax.defaultTaxRate': 'tax-id',
        });

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('systemConfigApiService', () => ({ getValues: getValuesMock }) as never);

        expect(await store.loadDefaultTaxRateId()).toBe('tax-id');
        expect(await store.loadDefaultTaxRateId()).toBe('tax-id');
        expect(getValuesMock).toHaveBeenCalledTimes(1);
    });

    it('reuses pending product number range id loads', async () => {
        const searchIdsMock = jest.fn().mockResolvedValue({ data: ['number-range-id'] });

        jest.spyOn(Date, 'now').mockReturnValue(1000);
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({ searchIds: searchIdsMock } as never);

        const firstLoad = store.loadProductNumberRangeIds();
        const secondLoad = store.loadProductNumberRangeIds();

        expect(await firstLoad).toStrictEqual(['number-range-id']);
        expect(await secondLoad).toStrictEqual(['number-range-id']);
        expect(searchIdsMock).toHaveBeenCalledTimes(1);
    });
});
