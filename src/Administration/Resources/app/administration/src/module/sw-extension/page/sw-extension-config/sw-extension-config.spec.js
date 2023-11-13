import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';
import 'src/app/component/meteor/sw-meteor-page';
import swExtensionConfigPage from 'src/module/sw-extension/page/sw-extension-config';
import swExtensionIcon from 'src/app/asyncComponent/extension/sw-extension-icon';

Shopware.Component.register('sw-extension-config', swExtensionConfigPage);
Shopware.Component.register('sw-extension-icon', swExtensionIcon);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/page/sw-extension-config-spec', () => {
    let wrapper;
    let SwExtensionConfig;
    let SwMeteorPage;

    async function createWrapper() {
        const localVue = createLocalVue();

        return shallowMount(SwExtensionConfig, {
            localVue,
            propsData: {
                namespace: 'MyExtension',
            },
            data() {
                return { extension: null };
            },
            mocks: {
                $route: {
                    meta: {
                        $module: null,
                    },
                },
            },
            stubs: {
                'sw-meteor-page': await Shopware.Component.build('sw-meteor-page'),
                'sw-search-bar': true,
                'sw-notification-center': true,
                'sw-help-center': true,
                'sw-meteor-navigation': true,
                'sw-external-link': true,
                'sw-system-config': true,
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-extension-icon': await Shopware.Component.build('sw-extension-icon'),
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
        });
    }

    beforeAll(async () => {
        SwExtensionConfig = await Shopware.Component.build('sw-extension-config');
        SwMeteorPage = await Shopware.Component.build('sw-meteor-page');
    });

    beforeEach(async () => {
        if (typeof Shopware.State.get('shopwareExtensions') !== 'undefined') {
            Shopware.State.unregisterModule('shopwareExtensions');
        }

        Shopware.State.registerModule('shopwareExtensions', {
            state: {
                myExtensions: { data: { length: 0, find: () => null } },
            },
        });
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('domain should suffix config', async () => {
        expect(wrapper.vm.domain).toBe('MyExtension.config');
    });

    it('Save click success', async () => {
        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.resolve(),
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledTimes(1);
    });

    it('Save click error', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.reject(),
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('shows default header', async () => {
        const iconComponent = wrapper.get('.sw-extension-config__extension-icon img');
        expect(iconComponent.attributes().src).toBe('administration/static/img/theme/default_theme_preview.jpg');
        expect(iconComponent.attributes().alt).toBe('sw-extension-store.component.sw-extension-config.imageDescription');

        const title = wrapper.get('.sw-meteor-page__smart-bar-title');
        expect(title.text()).toBe('MyExtension');

        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toBe('');
    });

    it('shows header for extension details', async () => {
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

        expect(page.props('fromLink')).toBe(fromRoute);
    });
});
