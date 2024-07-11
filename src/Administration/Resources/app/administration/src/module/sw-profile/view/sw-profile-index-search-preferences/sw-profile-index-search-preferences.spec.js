import { mount } from '@vue/test-utils';
import swProfileIndexSearchPreferences from 'src/module/sw-profile/view/sw-profile-index-search-preferences';

/**
 * @package services-settings
 */

Shopware.Component.register('sw-profile-index-search-preferences', swProfileIndexSearchPreferences);
Shopware.Service().register('shopwareDiscountCampaignService', () => {
    return {
        isDiscountCampaignActive: jest.fn(() => true),
    };
});

const swProfileStateMock = {
    namespaced: true,
    state() {
        return {
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },
    mutations: {
        setSearchPreferences(state, searchPreferences) {
            state.searchPreferences = searchPreferences;
        },
        setUserSearchPreferences(state, userSearchPreferences) {
            state.userSearchPreferences = userSearchPreferences;
        },
    },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-profile-index-search-preferences', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-checkbox-field': true,
                'sw-loader': true,
                'sw-extension-component-section': true,
                'sw-alert': true,
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                userConfigService: {
                    upsert: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve();
                    },
                },
                searchPreferencesService: {
                    getDefaultSearchPreferences: () => {},
                    getUserSearchPreferences: () => {},
                    processSearchPreferences: () => [],
                    createUserSearchPreferences: () => {
                        return {
                            key: 'search.preferences',
                            userId: 'userId',
                        };
                    },
                },
            },
            attachTo: document.body,
        },
    });
}

describe('src/module/sw-profile/view/sw-profile-index-search-preferences', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swProfile', swProfileStateMock);
    });

    beforeEach(() => {
        Shopware.Application.view.deleteReactive = () => {};
    });

    it('should get data source once component created', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.getDataSource = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getDataSource).toHaveBeenCalledTimes(1);
        wrapper.vm.getDataSource.mockRestore();
    });

    it('should update data source once component created', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.updateDataSource = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.updateDataSource).toHaveBeenCalled();
        wrapper.vm.updateDataSource.mockRestore();
    });

    it('should add event listeners once component created', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.addEventListeners = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.addEventListeners).toHaveBeenCalled();
        wrapper.vm.addEventListeners.mockRestore();
    });

    it('should remove event listeners before component destroyed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.removeEventListeners = jest.fn();

        await wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.removeEventListeners).toHaveBeenCalledTimes(1);
        wrapper.vm.removeEventListeners.mockRestore();
    });

    it('should get user search preferences once component created', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.searchPreferencesService.getUserSearchPreferences = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.searchPreferencesService.getUserSearchPreferences).toHaveBeenCalledTimes(1);
        wrapper.vm.searchPreferencesService.getUserSearchPreferences.mockRestore();
    });

    it('should be able to select all', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: false,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: false,
                    _score: 250,
                    group: [],
                },
            ],
        }]);

        await wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-select-all',
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: true,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: true,
                        }),
                    ]),
                }),
            ]),
        );
    });

    it('should be able to deselect all', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: true,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: true,
                    _score: 250,
                    group: [],
                },
            ],
        }]);

        await wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-deselect-all',
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: false,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: false,
                        }),
                    ]),
                }),
            ]),
        );
    });

    it('should be able to change search preference', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: false,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: false,
                },
                {
                    fieldName: 'productNumber',
                    _searchable: false,
                },
            ],
        }]);

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([expect.objectContaining({
                entityName: 'product',
                _searchable: true,
                fields: expect.arrayContaining([
                    expect.objectContaining({
                        fieldName: 'name',
                        _searchable: true,
                    }),
                    expect.objectContaining({
                        fieldName: 'productNumber',
                        _searchable: true,
                    }),
                ]),
            })]),
        );
    });

    it('should not be able to change search preference', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: false,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: true,
                },
                {
                    fieldName: 'productNumber',
                    _searchable: false,
                },
            ],
        }]);

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([expect.objectContaining({
                entityName: 'product',
                _searchable: true,
                fields: expect.arrayContaining([
                    expect.objectContaining({
                        fieldName: 'name',
                        _searchable: true,
                    }),
                    expect.objectContaining({
                        fieldName: 'productNumber',
                        _searchable: false,
                    }),
                ]),
            })]),
        );
    });

    it('should contain a computed property, calling: defaultSearchPreferences', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.searchPreferencesService.getDefaultSearchPreferences = jest.fn(() => [
            {
                order: {
                    documents: {
                        documentNumber: { _score: 80, _searchable: false },
                        documentInvoice: { _score: 80, _searchable: false },
                    },
                },
            },
        ]);

        await flushPromises();

        expect(wrapper.vm.defaultSearchPreferences).toEqual(expect.arrayContaining([
            expect.objectContaining({
                order: expect.objectContaining({
                    documents: expect.objectContaining({
                        documentNumber: expect.objectContaining({
                            _score: 80,
                            _searchable: false,
                        }),
                        documentInvoice: expect.objectContaining({
                            _score: 80,
                            _searchable: false,
                        }),
                    }),
                }),
            }),
        ]));

        await Shopware.State.commit('swProfile/setUserSearchPreferences', [
            {
                order: {
                    documents: {
                        documentNumber: { _score: 80, _searchable: true },
                        documentInvoice: { _score: 80, _searchable: true },
                    },
                },
            },
        ]);

        expect(wrapper.vm.defaultSearchPreferences).toEqual(expect.arrayContaining([
            expect.objectContaining({
                order: expect.objectContaining({
                    documents: expect.objectContaining({
                        documentNumber: expect.objectContaining({
                            _score: 80,
                            _searchable: true,
                        }),
                        documentInvoice: expect.objectContaining({
                            _score: 80,
                            _searchable: true,
                        }),
                    }),
                }),
            }),
        ]));
    });

    it('should merge defaultSearchPreferences and userSearchPreferences correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.searchPreferencesService.getDefaultSearchPreferences = jest.fn(() => [
            {
                order: {
                    _searchable: false,
                    number: { _score: 80, _searchable: true },
                    documents: {
                        _searchable: false,
                        documentNumber: { _score: 80, _searchable: false },
                        documentInvoice: { _score: 80, _searchable: false },
                    },
                },
            }, {
                category: {
                    _searchable: false,
                    name: { _score: 80, _searchable: true },
                    tags: {
                        _searchable: false,
                        name: { _score: 80, _searchable: true },
                    },
                },
            }, {
                notInUserPreferences: {
                    _searchable: true,
                    name: { _score: 80, _searchable: true },
                },
            },
        ]);

        await flushPromises();

        wrapper.vm.userSearchPreferences = [
            {
                order: {
                    _searchable: true,
                    number: { _score: 80, _searchable: true },
                    documents: {
                        _searchable: true,
                        documentNumber: { _score: 80, _searchable: true },
                        documentInvoice: { _score: 80, _searchable: true },
                    },
                },
            }, {
                category: {
                    _searchable: true,
                    name: { _score: 80, _searchable: false },
                    tags: {
                        _searchable: true,
                        name: { _score: 80, _searchable: false },
                    },
                },
            }, {
                notInDefaultPreferences: {
                    _searchable: true,
                    name: { _score: 80, _searchable: true },
                },
            },
        ];

        await flushPromises();

        const result = wrapper.vm.defaultSearchPreferences;

        expect(result).toHaveLength(3);
        expect(result).toEqual(expect.arrayContaining([
            expect.objectContaining({
                order: expect.objectContaining({
                    _searchable: true,
                    number: expect.objectContaining({
                        _score: 80,
                        _searchable: true,
                    }),
                    documents: expect.objectContaining({
                        _searchable: true,
                        documentNumber: expect.objectContaining({ _score: 80, _searchable: true }),
                        documentInvoice: expect.objectContaining({ _score: 80, _searchable: true }),
                    }),
                }),
            }),
            expect.objectContaining({
                category: expect.objectContaining({
                    _searchable: true,
                    name: expect.objectContaining({ _score: 80, _searchable: false }),
                    tags: expect.objectContaining({
                        _searchable: true,
                        name: expect.objectContaining({ _score: 80, _searchable: false }),
                    }),
                }),
            }),
            expect.objectContaining({
                notInUserPreferences: {
                    _searchable: true,
                    name: expect.objectContaining({ _score: 80, _searchable: true }),
                },
            }),
        ]));

        expect(result).toEqual(expect.not.arrayContaining([
            {
                notInDefaultPreferences: {
                    _searchable: true,
                    name: { _score: 80, _searchable: true },
                },
            },
        ]));
    });
});
