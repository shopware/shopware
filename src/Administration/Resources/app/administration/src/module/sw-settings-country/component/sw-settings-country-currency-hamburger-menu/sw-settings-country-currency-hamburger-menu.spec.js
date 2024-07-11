/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-country-currency-hamburger-menu', {
        sync: true,
    }), {
        props: {
            isLoading: false,
            options: [{}],
        },
        global: {
            renderStubDefaultSlot: true,
            directives: {
                tooltip: {},
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
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-popover': true,
                'sw-icon': {
                    template: '<div></div>',
                },
                'sw-checkbox-field': {
                    template: '<div class="checkbox"></div>',
                },
            },
        },
    });
}

describe('module/sw-settings-country/component/sw-settings-country-currency-hamburger-menu', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should able to show hamburger menu', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await flushPromises();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem).toHaveLength(wrapper.props().options.length);
    });

    it('should able to edit on hamburger menu', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await flushPromises();
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
        await flushPromises();

        await wrapper.find('.sw-settings-country-currency-hamburger-menu__button').trigger('click');
        await flushPromises();
        const hamburgerButton = wrapper.find('.sw-settings-country-currency-hamburger-menu__wrapper');
        expect(hamburgerButton.isVisible()).toBeTruthy();

        const hamburgerItem = wrapper.findAll('.sw-settings-country-currency-hamburger-menu__item');
        expect(hamburgerItem).toHaveLength(wrapper.props().options.length);
        expect(hamburgerItem.at(0).find('.checkbox').attributes().disabled).toBe('true');
    });
});
