import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-profile/view/sw-profile-index-search-preferences';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button';

const swProfileStateMock = {
    namespaced: true,
    state() {
        return {
            searchPreferences: [],
            userSearchPreferences: null
        };
    },
    mutations: {
        setSearchPreferences(state, searchPreferences) {
            state.searchPreferences = searchPreferences;
        },
        setUserSearchPreferences(state, userSearchPreferences) {
            state.userSearchPreferences = userSearchPreferences;
        }
    }
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-profile-index-search-preferences'), {
        localVue,
        stubs: {
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-container': Shopware.Component.build('sw-container'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-checkbox-field': true,
            'sw-loader': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve();
                    }
                })
            },
            userConfigService: {
                upsert: () => {
                    return Promise.resolve();
                },
                search: () => {
                    return Promise.resolve();
                }
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            searchPreferencesService: {
                getDefaultSearchPreferences: () => {},
                getUserSearchPreferences: () => {},
                createUserSearchPreferences: () => {
                    return {
                        key: 'search.preferences',
                        userId: 'userId'
                    };
                }
            }
        }
    });
}

describe('src/module/sw-profile/view/sw-profile-index-search-preferences', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swProfile', swProfileStateMock);
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get data source once component created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getDataSource = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getDataSource).toHaveBeenCalledTimes(1);
        wrapper.vm.getDataSource.mockRestore();
    });

    it('should add event listeners once component created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.addEventListeners = jest.fn();

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.addEventListeners).toHaveBeenCalledTimes(1);
        wrapper.vm.addEventListeners.mockRestore();
    });

    it('should remove event listeners before component destroyed', async () => {
        const wrapper = createWrapper();
        wrapper.vm.removeEventListeners = jest.fn();

        await wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.removeEventListeners).toHaveBeenCalledTimes(1);
        wrapper.vm.removeEventListeners.mockRestore();
    });

    it('should get user search preferences once component created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.searchPreferencesService.getUserSearchPreferences = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.searchPreferencesService.getUserSearchPreferences).toHaveBeenCalledTimes(1);
        wrapper.vm.searchPreferencesService.getUserSearchPreferences.mockRestore();
    });

    it('should be able to select all', async () => {
        const wrapper = createWrapper();
        wrapper.vm.acl.can = jest.fn(() => true);

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: false,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: false,
                    _score: 250,
                    group: []
                }
            ]
        }]);

        wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-select-all'
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: true,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: true
                        })
                    ])
                })
            ])
        );

        wrapper.vm.acl.can.mockRestore();
    });

    it('should not be able to select all', async () => {
        const wrapper = createWrapper();
        wrapper.vm.acl.can = jest.fn(() => false);

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: false,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: false,
                    _score: 250,
                    group: []
                }
            ]
        }]);

        wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-select-all'
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: false,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: false
                        })
                    ])
                })
            ])
        );

        wrapper.vm.acl.can.mockRestore();
    });

    it('should be able to deselect all', async () => {
        const wrapper = createWrapper();
        wrapper.vm.acl.can = jest.fn(() => true);

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: true,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: true,
                    _score: 250,
                    group: []
                }
            ]
        }]);

        wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-deselect-all'
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: false,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: false
                        })
                    ])
                })
            ])
        );

        wrapper.vm.acl.can.mockRestore();
    });

    it('should not be able to deselect all', async () => {
        const wrapper = createWrapper();
        wrapper.vm.acl.can = jest.fn(() => false);

        await Shopware.State.commit('swProfile/setSearchPreferences', [{
            entityName: 'product',
            _searchable: true,
            fields: [
                {
                    fieldName: 'name',
                    _searchable: true,
                    _score: 250,
                    group: []
                }
            ]
        }]);

        wrapper.find(
            '.sw-profile-index-search-preferences-searchable-elements__button-deselect-all'
        ).trigger('click');

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: true,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: true
                        })
                    ])
                })
            ])
        );

        wrapper.vm.acl.can.mockRestore();
    });
});
