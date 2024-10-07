/**
 * @package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-media', {
            sync: true,
        }),
        {
            created() {
                this.createdComponent();
            },

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
                    'mt-slider': true,
                    'sw-app-topbar-button': true,
                    'sw-notification-center': true,
                    'sw-help-center-v2': true,
                    'router-link': true,
                    'sw-app-actions': true,
                    'sw-button-deprecated': true,
                    'sw-sales-channel-switch': true,
                    'sw-alert': true,
                    'sw-form-field-renderer': true,
                    'sw-inherit-wrapper': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                },
                provide: {
                    systemConfigApiService: {
                        getConfig: () => {
                            return Promise.resolve([
                                {
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
                                },
                            ]);
                        },
                        getValues: () => {
                            return Promise.resolve({
                                'core.media.defaultEnableAugmentedReality': false,
                                'core.media.defaultLightIntensity': 100,
                            });
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-media/page/sw-settings-media', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should handle error on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createErrorNotification = jest.fn();
        wrapper.vm.systemConfigApiService.getValues = jest.fn(() => {
            // eslint-disable-next-line prefer-promise-reject-errors
            return Promise.reject({
                response: {
                    data: {
                        errors: [
                            {
                                code: '0',
                                detail: 'Oops!',
                            },
                        ],
                    },
                },
            });
        });

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.createErrorNotification).toHaveBeenCalled();
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
        expect(wrapper.find('.sw-card-view').find('.sw-system-config').find('.sw-card').exists()).toBeTruthy();
    });

    it('should change the slider value', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        wrapper.vm.onSliderChange(50);

        expect(wrapper.vm.sliderValue).toBe(50);
    });
});
