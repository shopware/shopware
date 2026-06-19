/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import swSettingsStorefrontConfiguration from './index';

Shopware.Component.register('sw-settings-storefront-configuration', swSettingsStorefrontConfiguration);

describe('sw-settings-storefront-configuration', () => {
    /**
     * @deprecated tag:v6.8.0 - This test will be removed with the deprecated component.
     */
    it('renders with required storefront settings', async () => {
        const component = await Shopware.Component.build('sw-settings-storefront-configuration');

        const wrapper = shallowMount(component, {
            props: {
                storefrontSettings: {
                    'core.storefrontSettings.iconCache': true,
                },
            },
            global: {
                stubs: {
                    'mt-switch': true,
                },
                provide: {
                    feature: {},
                },
            },
        });

        expect(wrapper.props('storefrontSettings')).toEqual({
            'core.storefrontSettings.iconCache': true,
        });
    });
});
