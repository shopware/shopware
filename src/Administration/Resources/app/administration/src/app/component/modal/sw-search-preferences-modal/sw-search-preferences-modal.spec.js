/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/modal/sw-search-preferences-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-search-preferences-modal'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-loader': true,
            'sw-data-grid': true,
            'sw-icon': true
        },
        provide: {
            searchPreferencesService: {
                getDefaultSearchPreferences: () => {},
                getUserSearchPreferences: () => {},
                createUserSearchPreferences: () => {
                    return {
                        key: 'search.preferences',
                        userId: 'userId'
                    };
                }
            },
            searchRankingService: {
                clearCacheUserSearchConfiguration: () => {}
            },
            userConfigService: {
                upsert: () => {
                    return Promise.resolve();
                },
                search: () => {
                    return Promise.resolve();
                }
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        }
    });
}

describe('src/app/component/modal/sw-search-preferences-modal', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.Application.view.deleteReactive = () => {};
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get data source once component created', async () => {
        wrapper.vm.getDataSource = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getDataSource).toHaveBeenCalledTimes(1);

        wrapper.vm.getDataSource.mockRestore();
    });

    it('should be able to turn off modal', async () => {
        await wrapper.find('.sw-search-preferences-modal__button-cancel').trigger('click');

        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });

    it('should call to user config service when saving changes', async () => {
        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        await wrapper.find('.sw-search-preferences-modal__button-save').trigger('click');

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalledTimes(1);

        wrapper.vm.userConfigService.upsert.mockRestore();
    });

    it('should be able to change search preference', async () => {
        await wrapper.setData({
            searchPreferences: [{
                entityName: 'product',
                _searchable: false,
                fields: [
                    {
                        fieldName: 'name',
                        _searchable: false
                    },
                    {
                        fieldName: 'productNumber',
                        _searchable: false
                    }
                ]
            }]
        });

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([expect.objectContaining({
                entityName: 'product',
                _searchable: true,
                fields: expect.arrayContaining([
                    expect.objectContaining({
                        fieldName: 'name',
                        _searchable: true
                    }),
                    expect.objectContaining({
                        fieldName: 'productNumber',
                        _searchable: true
                    }),
                ])
            })])
        );
    });

    it('should not be able to change search preference', async () => {
        await wrapper.setData({
            searchPreferences: [{
                entityName: 'product',
                _searchable: false,
                fields: [
                    {
                        fieldName: 'name',
                        _searchable: true
                    },
                    {
                        fieldName: 'productNumber',
                        _searchable: false
                    }
                ]
            }]
        });

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([expect.objectContaining({
                entityName: 'product',
                _searchable: true,
                fields: expect.arrayContaining([
                    expect.objectContaining({
                        fieldName: 'name',
                        _searchable: true
                    }),
                    expect.objectContaining({
                        fieldName: 'productNumber',
                        _searchable: false
                    }),
                ])
            })])
        );
    });
});
