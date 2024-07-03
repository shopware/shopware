import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-media', {
        sync: true,
    }), {

        global: {
            mocks: {
                $route: {
                    params: {},
                    meta: {
                        $module: {
                            icon: 'default-symbol-content',
                        },
                    },
                },
            },
            stubs: {
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-icon': true,
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-skeleton': true,
                'sw-system-config': await wrapTestComponent('sw-system-config'),
                'sw-search-bar': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-loader': true,
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
                'sw-error-summary': true,
            },
        },
        provide: {
            systemConfigApiService: {
                getConfig: () => {
                    return Promise.resolve([{
                        title: {
                            'en-GB': '3D Files',
                        },
                        name: null,
                        elements: [
                            {
                                name: 'core.media.defaultEnableAugmentedReality',
                                type: 'bool',
                                config: {
                                    label: {
                                        'en-GB': 'enableAugmentedRealityDefault',
                                    },
                                    helpText: {
                                        'en-GB': 'enableAugmentedRealityDefault.helptext',
                                    },
                                },
                            },
                        ],
                    }]);
                },
                getValues: () => {
                    return Promise.resolve({
                        'core.media.defaultEnableAugmentedReality': false,
                    });
                },
            },
        },
    });
}

describe('module/sw-settings-media/page/sw-settings-media', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should save system config failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Oops!'));
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
    });

    it('should finish saving correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.onLoadingChanged = jest.fn();
        wrapper.vm.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should finish saving failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            isSaveSuccessful: true,
        });

        wrapper.vm.saveFinish();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should contain the settings card', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        expect(
            wrapper.find('.sw-card-view')
                .find('.sw-system-config')
                .find('.sw-card')
                .exists(),
        ).toBeTruthy();
    });
});
