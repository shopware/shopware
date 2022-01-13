import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-config';
import 'src/app/component/base/sw-button';
import 'src/app/component/meteor/sw-meteor-page';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-extension-config'), {
        localVue,
        propsData: {
            namespace: 'MyExtension'
        },
        data() {
            return { extension: null };
        },
        mocks: {
            $route: {
                meta: {
                    $module: null
                }
            }
        },
        stubs: {
            'sw-meteor-page': Shopware.Component.build('sw-meteor-page'),
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-meteor-navigation': true,
            'sw-external-link': true,
            'sw-system-config': true,
            'sw-button': Shopware.Component.build('sw-button')
        },
        provide: {
            shopwareExtensionService: {
                updateExtensionData: jest.fn()
            },
            systemConfigApiService: {
                getValues: () => {
                    return Promise.resolve({
                        'core.store.apiUri': 'https://api.shopware.com',
                        'core.store.licenseHost': 'sw6.test.shopware.in',
                        'core.store.shopSecret': 'very.s3cret',
                        'core.store.shopwareId': 'max@muster.com'
                    });
                }
            }
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-my-extensions-account', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('domain should suffix config', () => {
        expect(wrapper.vm.domain).toBe('MyExtension.config');
    });

    it('Save click success', async () => {
        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.resolve()
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationSuccess).toBeCalledTimes(1);
    });

    it('Save click error', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.reject()
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationError).toBeCalledTimes(1);
    });

    it('shows default header', async () => {
        const iconComponent = wrapper.get('.sw-extension-config__extension-icon');
        expect(iconComponent.attributes().src).toEqual('administration/static/img/theme/default_theme_preview.jpg');
        expect(iconComponent.attributes().alt).toEqual('sw-extension-store.component.sw-extension-config.imageDescription');

        const title = wrapper.get('.sw-meteor-page__smart-bar-title');
        expect(title.text()).toEqual('MyExtension');

        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toEqual('');
    });

    it('shows header for extension details', async () => {
        wrapper.vm.extension = {
            icon: 'icon.png',
            label: 'My extension label',
            producerName: 'shopware AG'
        };

        await wrapper.vm.$nextTick();
        const iconComponent = wrapper.get('.sw-extension-config__extension-icon');
        expect(iconComponent.attributes().src).toEqual('icon.png');
        expect(iconComponent.attributes().alt).toEqual('sw-extension-store.component.sw-extension-config.imageDescription');

        const title = wrapper.get('.sw-meteor-page__smart-bar-title');
        expect(title.text()).toEqual('My extension label');

        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toEqual('sw-extension-store.component.sw-extension-config.labelBy shopware AG');
    });

    it('shows header for extension details with producer website', async () => {
        wrapper.vm.extension = {
            producerName: 'shopware AG',
            producerWebsite: 'https://www.shopware.com/'
        };

        await wrapper.vm.$nextTick();
        const meta = wrapper.get('.sw-meteor-page__smart-bar-meta');
        expect(meta.text()).toContain('sw-extension-store.component.sw-extension-config.labelBy');

        const metaLink = wrapper.get('.sw-extension-config__producer-link');
        expect(metaLink.attributes().href).toEqual('https://www.shopware.com/');
        expect(metaLink.text()).toEqual('shopware AG');
    });
});
