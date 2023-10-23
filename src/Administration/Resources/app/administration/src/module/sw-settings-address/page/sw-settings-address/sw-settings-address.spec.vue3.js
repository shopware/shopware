import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-address', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        searchIds: () => Promise.resolve(
                            {
                                data: [1],
                                total: 1,
                            },
                        ),
                    }),
                },
            },
            mocks: {
                $route: { query: '' },
                $router: { resolve: () => ({ href: 'the_link' }) },
            },
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-icon': true,
                'sw-search-bar': true,
                'sw-card-view': true,
                'sw-button-process': true,
                'sw-system-config': true,
                'sw-alert': true,
                'sw-skeleton': true,
            },
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

        expect(wrapper.vm.defaultCountry).toBe(1);
    });

    it('should be shown sw-alert component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('sw-alert-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-alert-stub span').text())
            .toBe('sw-settings-address.general.textWarning');
        expect(
            wrapper.find('sw-alert-stub').element.style.getPropertyValue('display'),
        ).toBe('');
    });

    it('should be hide sw-alert component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: true,
        });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(true);
        expect(
            wrapper.find('sw-alert-stub').element.style.getPropertyValue('display'),
        ).toBe('none');
    });
});
