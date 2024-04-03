import { mount } from '@vue/test-utils';
import extensionStore from 'src/module/sw-extension/store/extensions.store';
import ShopwareExtensionService from 'src/module/sw-extension/service/shopware-extension.service';

Shopware.State.registerModule('shopwareExtensions', extensionStore);

async function createWrapper() {
    const shopwareExtensionService = new ShopwareExtensionService();

    return mount(await wrapTestComponent('sw-extension-adding-failed', { sync: true }), {
        global: {
            stubs: {
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
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
 * @package services-settings
 */
describe('src/module/sw-extension-component/sw-extension-adding-failed', () => {
    it('passes correct props to sw-circle-icon', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-circle-icon').props('variant')).toBe('danger');
        expect(wrapper.getComponent('.sw-circle-icon').props('size')).toBe(72);
        expect(wrapper.getComponent('.sw-circle-icon').props('iconName')).toBe('regular-times-circle-s');
    });

    it('has a primary block button', async () => {
        Shopware.State.commit('shopwareExtensions/myExtensions', []);

        const wrapper = await createWrapper();

        const closeButton = wrapper.getComponent('button.sw-button');

        expect(closeButton.classes('sw-button--primary')).toBe(true);
        expect(closeButton.classes('sw-button--block')).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        Shopware.State.commit('shopwareExtensions/myExtensions', []);

        const wrapper = await createWrapper();

        await wrapper.get('button.sw-button').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });

    it('renders all information if extension is rent', async () => {
        Shopware.State.commit('shopwareExtensions/myExtensions', [{
            name: 'test-app',
            storeLicense: {
                variant: 'rent',
            },
        }]);

        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-extension-adding-failed__text-licence-cancellation').text()).toBe('sw-extension-store.component.sw-extension-adding-failed.installationFailed.notificationLicense');
    });

    it('does not render additional information if the license is not a subscription', async () => {
        Shopware.State.commit('shopwareExtensions/myExtensions', [{
            name: 'test-app',
            storeLicense: {
                variant: 'buy',
            },
        }]);

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-installation-failed__text-licence-cancellation')
            .exists())
            .toBe(false);
        expect(wrapper.find('h3').text())
            .toBe(
                'sw-extension-store.component.sw-extension-adding-failed.installationFailed.titleFailure',
            );
        expect(wrapper.find('p').text())
            .toBe(
                'sw-extension-store.component.sw-extension-adding-failed.installationFailed.textProblem',
            );
    });

    // eslint-disable-next-line max-len
    it('does not render additional information about licenses and uses general failure text if extension is not licensed', async () => {
        Shopware.State.commit('shopwareExtensions/myExtensions', []);

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-installation-failed__text-licence-cancellation')
            .exists()).toBe(false);
        expect(wrapper.find('h3')
            .text()).toBe('sw-extension-store.component.sw-extension-adding-failed.titleFailure');
        expect(wrapper.find('p')
            .text()).toBe('sw-extension-store.component.sw-extension-adding-failed.textProblem');
    });
});
