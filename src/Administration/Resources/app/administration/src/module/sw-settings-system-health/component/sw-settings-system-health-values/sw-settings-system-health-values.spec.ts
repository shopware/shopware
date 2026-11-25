/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('module/sw-settings-system-health/component/sw-settings-system-health-values', () => {
    it('should list each value under its label, with the full value on hover', async () => {
        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-values', { sync: true }), {
            props: { items: [{ key: 'appUrl', label: 'App URL', value: 'https://shop.example' }] },
        });

        expect(wrapper.find('dt').text()).toBe('App URL');
        expect(wrapper.find('dd').text()).toBe('https://shop.example');
        expect(wrapper.find('dd').attributes('title')).toBe('https://shop.example');
    });
});
