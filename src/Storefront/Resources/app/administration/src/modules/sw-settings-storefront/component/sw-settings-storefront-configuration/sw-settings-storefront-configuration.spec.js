/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import './index';

describe('sw-settings-storefront-configuration', () => {
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
                    'sw-switch-field': true,
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
