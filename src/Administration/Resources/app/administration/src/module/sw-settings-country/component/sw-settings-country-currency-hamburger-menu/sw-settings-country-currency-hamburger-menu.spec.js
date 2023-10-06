/**
 * @package buyers-experience
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsCountryCurrencyHamburgerMenu from 'src/module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/base/sw-button';

Shopware.Component.register('sw-settings-country-currency-hamburger-menu', swSettingsCountryCurrencyHamburgerMenu);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-country-currency-hamburger-menu'), {
        localVue,

        propsData: {
            isLoading: false,
            options: [{}],
        },

        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
            feature: {
                isActive: () => true,
            },
        },

        stubs: {
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-popover': true,
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-checkbox-field': {
                template: '<div class="checkbox"></div>',
            },
        },
    });
}

describe('module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should able to show hamburger menu', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem).toHaveLength(wrapper.props().options.length);
    });

    it('should able to edit on hamburger menu', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem).toHaveLength(wrapper.props().options.length);
        expect(hamburgerItem.at(0).find('.checkbox').attributes().disabled).toBeUndefined();
    });

    it('should not able to edit on hamburger menu', async () => {
        const wrapper = await createWrapper([
            'country.viewer',
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem).toHaveLength(wrapper.props().options.length);
        expect(hamburgerItem.at(0).find('.checkbox').attributes().disabled).toBe('disabled');
    });
});
