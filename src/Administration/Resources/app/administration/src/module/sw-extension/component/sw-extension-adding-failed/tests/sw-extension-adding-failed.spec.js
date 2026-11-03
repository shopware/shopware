import { mount } from '@vue/test-utils';
import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';

const licenceCancellationTextSelector = '.sw-extension-adding-failed__text-licence-cancellation';

async function createWrapper() {
    const shopwareExtensionService = new ShopwareExtensionService();

    return mount(await wrapTestComponent('sw-extension-adding-failed', { sync: true }), {
        global: {
            stubs: {
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'i18n-t': {
                    template: '<div class="i18n-stub"><slot></slot></div>',
                },
                'sw-label': true,
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                shopwareExtensionService,
            },
        },
        props: {
            extensionName: 'test-app',
        },
    });
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension-component/sw-extension-adding-failed', () => {
    it('passes correct props to sw-circle-icon', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-circle-icon').props('variant')).toBe('danger');
        expect(wrapper.getComponent('.sw-circle-icon').props('size')).toBe(72);
        expect(wrapper.getComponent('.sw-circle-icon').props('iconName')).toBe('regular-times-circle-s');
    });

    it('has a primary block button', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([]);

        const wrapper = await createWrapper();

        const closeButton = wrapper.findByText('button', 'global.default.close');

        expect(closeButton.classes().some((cls) => cls.includes('--primary'))).toBe(true);
        expect(closeButton.classes().some((cls) => cls.includes('--block'))).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([]);

        const wrapper = await createWrapper();

        await wrapper.findByText('button', 'global.default.close').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });

    it('renders all information if extension has an active rent license', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            {
                name: 'test-app',
                storeLicense: {
                    variant: 'rent',
                    expirationDate: null,
                },
            },
        ]);

        const wrapper = await createWrapper();

        expect(wrapper.get(licenceCancellationTextSelector).text()).toBe(
            'sw-extension-store.component.sw-extension-adding-failed.installationFailed.notificationLicense',
        );
    });

    it('does not render additional information if the rent license is cancelled', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            {
                name: 'test-app',
                storeLicense: {
                    variant: 'rent',
                    expirationDate: '2025-08-01T03:30:35+01:00',
                },
            },
        ]);

        const wrapper = await createWrapper();

        expect(wrapper.find(licenceCancellationTextSelector).exists()).toBe(false);
    });

    it('does not render additional information if the license is not a subscription', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            {
                name: 'test-app',
                storeLicense: {
                    variant: 'buy',
                },
            },
        ]);

        const wrapper = await createWrapper();

        expect(wrapper.find(licenceCancellationTextSelector).exists()).toBe(false);
        expect(wrapper.find('h3').text()).toBe(
            'sw-extension-store.component.sw-extension-adding-failed.installationFailed.titleFailure',
        );
        expect(wrapper.find('p').text()).toBe(
            'sw-extension-store.component.sw-extension-adding-failed.installationFailed.textProblem',
        );
    });

    it('does not render additional information about licenses and uses general failure text if extension is not licensed', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([]);

        const wrapper = await createWrapper();

        expect(wrapper.find(licenceCancellationTextSelector).exists()).toBe(false);
        expect(wrapper.find('h3').text()).toBe('sw-extension-store.component.sw-extension-adding-failed.titleFailure');
        expect(wrapper.find('p').text()).toBe('sw-extension-store.component.sw-extension-adding-failed.textProblem');
    });
});
