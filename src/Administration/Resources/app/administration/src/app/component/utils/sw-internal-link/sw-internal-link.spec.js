/**
 * @package admin
 */

import { shallowMount, RouterLinkStub } from '@vue/test-utils';
import 'src/app/component/utils/sw-internal-link';

// initial component setup
const setup = async (propOverride) => {
    const propsData = {
        routerLink: { name: 'sw.product.index' },
        ...propOverride
    };

    return shallowMount(await Shopware.Component.build('sw-internal-link'), {
        stubs: {
            'sw-icon': true,
            RouterLink: RouterLinkStub
        },
        slots: {
            default: 'test internal link'
        },
        propsData
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

        expect(wrapper.findComponent(RouterLinkStub).props().to).toEqual({ name: 'sw.product.index' });
    });

    it('should add inline class if it is an inline link', async () => {
        const wrapper = await setup({ inline: true });

        expect(wrapper.findComponent(RouterLinkStub).classes()).toContain('sw-internal-link--inline');
    });
});
