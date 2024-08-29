/**
 * @package services-settings
 * @group disabledCompat
 */
import SearchPreferencesService from 'src/app/service/search-preferences.service';
import orderDefaultSearchConfiguration from 'src/module/sw-order/default-search-configuration';

describe('searchPreferencesService', () => {
    it('is registered correctly', () => {
        let searchPreferencesService = new SearchPreferencesService({
            userConfigRepository: Shopware.Service('repositoryFactory').create('user_config'),
        });
        searchPreferencesService = {
            createUserSearchPreferences: jest.fn(),
            getDefaultSearchPreferences: jest.fn(),
            getUserSearchPreferences: jest.fn(),
            processSearchPreferences: jest.fn(),
            processSearchPreferencesFields: jest.fn(),
        };

        expect(searchPreferencesService).toEqual(expect.objectContaining({
            createUserSearchPreferences: searchPreferencesService.createUserSearchPreferences,
            getDefaultSearchPreferences: searchPreferencesService.getDefaultSearchPreferences,
            getUserSearchPreferences: searchPreferencesService.getUserSearchPreferences,
            processSearchPreferences: searchPreferencesService.processSearchPreferences,
            processSearchPreferencesFields: searchPreferencesService.processSearchPreferencesFields,
        }));
    });

    describe('processSearchPreferences', () => {
        it('returns data correctly', async () => {
            const searchPreferencesService = new SearchPreferencesService({
                userConfigRepository: Shopware.Service('repositoryFactory').create('user_config'),
            });
            const searchPreferences = await searchPreferencesService.processSearchPreferences([orderDefaultSearchConfiguration]);

            expect(searchPreferences).toEqual(expect.arrayContaining([
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
            ]));
        });
    });
});
