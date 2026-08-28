/**
 * @sw-package inventory
 */
import SearchPreferencesService from 'src/app/service/search-preferences.service';
import orderDefaultSearchConfiguration from 'src/module/sw-order/default-search-configuration';

describe('searchPreferencesService', () => {
    beforeEach(() => {
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
    });

    it('is registered correctly', () => {
        let searchPreferencesService = new SearchPreferencesService();
        searchPreferencesService = {
            createUserSearchPreferences: jest.fn(),
            getDefaultSearchPreferences: jest.fn(),
            getUserSearchPreferences: jest.fn(),
            processSearchPreferences: jest.fn(),
            processSearchPreferencesFields: jest.fn(),
        };

        expect(searchPreferencesService).toEqual(
            expect.objectContaining({
                createUserSearchPreferences: searchPreferencesService.createUserSearchPreferences,
                getDefaultSearchPreferences: searchPreferencesService.getDefaultSearchPreferences,
                getUserSearchPreferences: searchPreferencesService.getUserSearchPreferences,
                processSearchPreferences: searchPreferencesService.processSearchPreferences,
                processSearchPreferencesFields: searchPreferencesService.processSearchPreferencesFields,
            }),
        );
    });

    describe('processSearchPreferences', () => {
        it('returns data correctly', async () => {
            const searchPreferencesService = new SearchPreferencesService();
            const searchPreferences = await searchPreferencesService.processSearchPreferences([
                orderDefaultSearchConfiguration,
            ]);

            expect(searchPreferences).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({
                        fields: [
                            {
                                _score: 80,
                                _searchable: false,
                                fieldName: 'documentNumber',
                                group: [
                                    {
                                        _score: 80,
                                        _searchable: false,
                                        fieldName: 'config.documentNumber',
                                    },
                                ],
                            },
                        ],
                    }),
                ]),
            );
        });
    });

    describe('createUserSearchPreferences', () => {
        it('returns the current user preference shell', () => {
            Shopware.Store.get('session').setCurrentUser({
                id: 'user-id',
            });
            const searchPreferencesService = new SearchPreferencesService();

            expect(searchPreferencesService.createUserSearchPreferences()).toEqual({
                key: 'search.preferences',
                userId: 'user-id',
            });
        });
    });
});
