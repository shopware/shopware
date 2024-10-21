/**
 * @package admin
 */

import { mount, RouterLinkStub } from '@vue/test-utils';

// initial component setup
const setup = async (propOverride) => {
    const props = {
        routerLink: { name: 'sw.product.index' },
        ...propOverride,
    };

    return mount(await wrapTestComponent('sw-internal-link', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
                RouterLink: process.env.DISABLE_JEST_COMPAT_MODE
                    ? RouterLinkStub
                    : {
                          name: 'RouterLinkStub',

                          props: {
                              to: {
                                  type: [
                                      String,
                                      Object,
                                  ],
                                  required: true,
                              },
                              custom: {
                                  type: Boolean,
                                  default: false,
                              },
                          },

                          render(h) {
                              // mock reasonable return values to mimic vue-router's useLink
                              const children = this.$slots?.default;
                              return this.custom ? children : h('a', undefined, children);
                          },
                      },
            },
        },
        slots: {
            default: 'test internal link',
        },
        props,
    });
};

describe('components/utils/sw-internal-link', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await setup();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        const wrapper = await setup();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should render correctly when disabled', async () => {
        const wrapper = await setup({ disabled: true });
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should display a custom icon', async () => {
        const wrapper = await setup({ icon: 'default-test-icon' });

        expect(wrapper.find('sw-icon-stub').attributes().name).toBe('default-test-icon');
    });

    it('should add custom target to link', async () => {
        const wrapper = await setup({ target: '_blank' });

        expect(wrapper.findComponent({ name: 'RouterLinkStub' }).props().to).toEqual({ name: 'sw.product.index' });
    });

    it('should add inline class if it is an inline link', async () => {
        const wrapper = await setup({ inline: true });

        expect(wrapper.findComponent({ name: 'RouterLinkStub' }).classes()).toContain('sw-internal-link--inline');
    });

    it('should allow links without router-links', async () => {
        const wrapper = await setup({
            routerLink: undefined,
        });

        expect(wrapper.find('a').exists()).toBe(true);
    });

    it('should emit click event on non-router links', async () => {
        const wrapper = await setup({
            routerLink: undefined,
        });

        expect(wrapper.emitted('click')).toBeFalsy();

        await wrapper.find('a').trigger('click');

        expect(wrapper.emitted('click')[0]).toEqual([]);
    });
});
