import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/base/sw-button';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-currency-hamburger-menu'), {
        localVue,

        propsData: {
            isLoading: false,
            options: [{}]
        },

        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-context-menu': Shopware.Component.build('sw-context-menu'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-popover': true,
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-checkbox-field': {
                template: '<div class="checkbox"></div>'
            }
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should able to show hamburger menu', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem.length).toBe(wrapper.props().options.length);
    });

    it('should able to edit on hamburger menu', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);
        await wrapper.vm.$nextTick();

        wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem.length).toBe(wrapper.props().options.length);
        expect(hamburgerItem.at(0).find('.checkbox').attributes().disabled).toBeUndefined();
    });

    it('should not able to edit on hamburger menu', async () => {
        const wrapper = createWrapper([
            'country.viewer'
        ]);
        await wrapper.vm.$nextTick();

        wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await wrapper.vm.$nextTick();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem.length).toBe(wrapper.props().options.length);
        expect(hamburgerItem.at(0).find('.checkbox').attributes().disabled).toBe('disabled');
    });
});
