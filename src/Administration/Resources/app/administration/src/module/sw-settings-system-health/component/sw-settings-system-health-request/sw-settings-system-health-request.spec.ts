/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props: Record<string, unknown>) {
    return mount(await wrapTestComponent('sw-settings-system-health-request', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-settings-system-health-response-code': {
                    props: ['statusCode'],
                    template: '<span class="code">{{ statusCode }}</span>',
                },
            },
        },
    });
}

describe('module/sw-settings-system-health/component/sw-settings-system-health-request', () => {
    it('should show the response code, URL and duration of a request', async () => {
        const wrapper = await createWrapper({ url: 'https://shop.example', statusCode: 200, durationMs: 41.6 });

        expect(wrapper.find('.code').text()).toBe('200');
        expect(wrapper.find('.sw-settings-system-health-request__url').text()).toBe('https://shop.example');
        expect(wrapper.find('.sw-settings-system-health-request__duration').text()).toBe(
            'sw-settings-system-health.request.duration',
        );
        expect(wrapper.find('.sw-settings-system-health-request__error').exists()).toBe(false);
    });

    it('should show the error of a failed request', async () => {
        const wrapper = await createWrapper({ url: 'https://shop.example', statusCode: 503, error: 'Service unavailable' });

        expect(wrapper.find('.sw-settings-system-health-request__error').text()).toBe('Service unavailable');
    });
});
