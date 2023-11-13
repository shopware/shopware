/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-external-link';

// initial component setup
const setup = async (propOverride) => {
    const propsData = {
        ...propOverride,
    };

    return shallowMount(await Shopware.Component.build('sw-external-link'), {
        stubs: ['sw-icon'],
        slots: {
            default: 'test external link',
        },
        propsData,
    });
};

describe('components/utils/sw-external-link', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await setup({ href: 'https://google.com' });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        const wrapper = await setup({ href: 'https://google.com' });
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should display a custom icon', async () => {
        const wrapper = await setup({
            href: 'https://google.com',
            icon: 'default-test-icon',
        });

        expect(wrapper.find('sw-icon-stub').attributes().name).toBe('default-test-icon');
        expect(wrapper.find('sw-icon-stub').attributes().size).toBe('10px');
    });

    it('should emit click event if no href is provided', async () => {
        const wrapper = await setup();

        await wrapper.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted().click).toBeTruthy();
    });

    it('should render small', async () => {
        const wrapper = await setup({
            href: 'https://google.com',
            small: true,
        });

        expect(wrapper.find('sw-icon-stub').attributes().size).toBe('8px');

        expect(wrapper.classes()).toContain('sw-external-link--small');
    });
});
