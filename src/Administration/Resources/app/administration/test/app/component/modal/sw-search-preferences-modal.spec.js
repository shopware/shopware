import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/modal/sw-search-preferences-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-search-preferences-modal'), {
        localVue,
        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-loader': true,
            'sw-data-grid': true,
            'sw-icon': true
        },
        provide: {
            acl: {
                can: () => true
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

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get data source once component created', async () => {
        wrapper.vm.getDataSource = jest.fn(() => Promise.resolve());

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.getDataSource).toHaveBeenCalledTimes(1);

        wrapper.vm.getDataSource.mockRestore();
    });

    it('should be able to turn off modal', async () => {
        wrapper.find('.sw-search-preferences-modal__button-cancel').trigger('click');

        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });

    it('should call to user config service when saving changes', () => {
        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        wrapper.find('.sw-search-preferences-modal__button-save').trigger('click');

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalledTimes(1);

        wrapper.vm.userConfigService.upsert.mockRestore();
    });
});
