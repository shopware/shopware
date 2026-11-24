/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props: Record<string, unknown>) {
    return mount(await wrapTestComponent('sw-settings-system-health-response-code', { sync: true }), {
        props,
        global: { stubs: { 'sw-status': { props: ['color'], template: '<span :data-color="color"><slot></slot></span>' } } },
    });
}

describe('module/sw-settings-system-health/component/sw-settings-system-health-response-code', () => {
    it.each([
        [{ statusCode: 204 }, 'green', '204'],
        [{ statusCode: 503 }, 'red', '503'],
        [{ successful: false }, 'red', 'sw-settings-system-health.request.failed'],
        [{}, 'gray', 'sw-settings-system-health.request.no-response'],
    ])('should show %o as a %s pill', async (props, color, text) => {
        const wrapper = await createWrapper(props);

        expect(wrapper.find('span').attributes('data-color')).toBe(color);
        expect(wrapper.text()).toBe(text);
    });
});
