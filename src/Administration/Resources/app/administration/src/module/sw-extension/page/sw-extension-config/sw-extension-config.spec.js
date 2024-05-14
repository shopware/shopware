import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/page/sw-extension-config-spec', () => {
    let SwExtensionConfig;
    let SwMeteorPage;

    async function createWrapper() {
        return mount(SwExtensionConfig, {
            global: {
                mocks: {
                    $route: {
                        meta: {
                            $module: null,
                        },
                    },
                },
                stubs: {
                    'sw-meteor-page': await wrapTestComponent('sw-meteor-page', { sync: true }),
                    'sw-system-config': await wrapTestComponent('sw-system-config', { sync: true }),
                    'sw-extension-icon': await wrapTestComponent('sw-extension-icon', { sync: true }),
                },
                provide: {
                    shopwareExtensionService: {
                        updateExtensionData: jest.fn(),
                    },
                    systemConfigApiService: {
                        getValues: () => {
                            return Promise.resolve({
                                'core.store.apiUri': 'https://api.shopware.com',
                                'core.store.licenseHost': 'sw6.test.shopware.in',
                                'core.store.shopSecret': 'very.s3cret',
                                'core.store.shopwareId': 'max@muster.com',
                            });
                        },
                    },
                },
            },
            props: {
                namespace: 'MyExtension',
            },
            data() {
                return { extension: null };
            },
        });
    }

    beforeAll(async () => {
        SwExtensionConfig = await wrapTestComponent('sw-extension-config', { sync: true });
        SwMeteorPage = await wrapTestComponent('sw-meteor-page', { sync: true });
    });

    beforeEach(async () => {
        if (typeof Shopware.State.get('shopwareExtensions') !== 'undefined') {
            Shopware.State.unregisterModule('shopwareExtensions');
        }

        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                myExtensions: { data: [] },
            },
            mutations: {
                setMyExtensions(state, extensions) {
                    state.myExtensions = extensions;
                },
            },
        });
    });

    it('domain should suffix config', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.domain).toBe('MyExtension.config');
    });

    it('should reload extensions on createdComponent', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.shopwareExtensionService.updateExtensionData).toHaveBeenCalledTimes(1);
    });

    it('should not reload extensions on createdComponent if extensions are loaded', async () => {
        Shopware.State.commit('shopwareExtensions/setMyExtensions', { data: [{ name: 'test-extension' }] });
        const wrapper = await createWrapper();

        expect(wrapper.vm.shopwareExtensionService.updateExtensionData).toHaveBeenCalledTimes(0);
    });

    it('Save click success', async () => {
        const wrapper = await createWrapper();

        const saveAllMock = jest.fn(() => Promise.resolve());
        const notificationMock = jest.fn();

        wrapper.vm.createNotificationSuccess = notificationMock;
        wrapper.vm.$refs.systemConfig.saveAll = saveAllMock;

        await wrapper.get('.sw-extension-config__save-action').trigger('click');

        expect(saveAllMock).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledTimes(1);
    });

    it('Save click error', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.reject(),
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('shows default header', async () => {
        const wrapper = await createWrapper();

        const iconComponent = wrapper.get('.sw-extension-config__extension-icon img');
        expect(iconComponent.attributes().src).toBe('administration/static/img/theme/default_theme_preview.jpg');
        expect(iconComponent.attributes().alt).toBe('sw-extension-store.component.sw-extension-config.imageDescription');

        const title = wrapper.get('.sw-meteor-page__smart-bar-title');
        expect(title.text()).toBe('MyExtension');

        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toBe('');
    });

    it('shows header for extension details', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.extension = {
            icon: 'icon.png',
            label: 'My extension label',
            producerName: 'shopware AG',
        };

        await wrapper.vm.$nextTick();
        const iconComponent = wrapper.get('.sw-extension-icon img');
        expect(iconComponent.attributes().src).toBe('icon.png');
        expect(iconComponent.attributes().alt).toBe('sw-extension-store.component.sw-extension-config.imageDescription');

        const title = wrapper.get('.sw-meteor-page__smart-bar-title');
        expect(title.text()).toBe('My extension label');

        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toBe('sw-extension-store.component.sw-extension-config.labelBy shopware AG');
    });

    it('shows header for extension details with producer website', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.extension = {
            producerName: 'shopware AG',
            producerWebsite: 'https://www.shopware.com/',
        };

        await wrapper.vm.$nextTick();
        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toContain('sw-extension-store.component.sw-extension-config.labelBy');

        const metaLink = wrapper.get('.sw-extension-config__producer-link');
        expect(metaLink.attributes().href).toBe('https://www.shopware.com/');
        expect(metaLink.text()).toBe('shopware AG');
    });

    it('saves from route when router navigates to sw-extension-config page', async () => {
        const wrapper = await createWrapper();

        const fromRoute = {
            name: 'from.route.name',
        };

        SwExtensionConfig.beforeRouteEnter.call(
            wrapper.vm,
            undefined,
            fromRoute,
            (c) => c(wrapper.vm),
        );
        await wrapper.vm.$nextTick();

        const page = wrapper.findComponent(SwMeteorPage);

        expect(page.props('fromLink')).toEqual(fromRoute);
    });
});
