/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('module/sw-settings-system-health/component/sw-settings-system-health-text', () => {
    it('should set URLs and constants apart as code', async () => {
        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-text', { sync: true }), {
            props: { text: 'APP_URL https://shop.example does not point to this shop.' },
        });

        expect(wrapper.findAll('code').map((code) => code.text())).toEqual(['APP_URL', 'https://shop.example']);
        expect(wrapper.text()).toBe('APP_URL https://shop.example does not point to this shop.');
    });
});
