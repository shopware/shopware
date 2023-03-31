import { shallowMount, createLocalVue } from '@vue/test-utils';
import swSettingsAddress from 'src/module/sw-settings-address/page/sw-settings-address';

Shopware.Component.register('sw-settings-address', swSettingsAddress);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-address'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    searchIds: () => Promise.resolve(
                        {
                            data: [1],
                            total: 1
                        }
                    ),
                })
            }
        },
        mocks: {
            $route: { query: '' },
        },
        stubs: {
            'sw-page': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-card-view': true,
            'sw-button-process': true,
            'sw-system-config': true,
            'sw-alert': true,
            'sw-skeleton': true,
        },
    });
}

describe('src/module/sw-settings-address/page/sw-settings-address', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get the default country with germany country', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.defaultCountry).toEqual(1);
    });

    it('should be shown sw-alert component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('sw-alert-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-alert-stub span').text())
            .toEqual('sw-settings-address.general.textWarning');
        expect(
            wrapper.find('sw-alert-stub').element.style.getPropertyValue('display')
        ).toEqual('');
    });

    it('should be hide sw-alert component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: true,
        });

        expect(wrapper.vm.isLoading).toBe(true);
        expect(
            wrapper.find('sw-alert-stub').element.style.getPropertyValue('display')
        ).toEqual('none');
    });
});
