/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-app-url', () => {
    it('should show the verification request with what went wrong', async () => {
        const check: SystemHealthCheck = {
            name: 'AppUrlVerification',
            category: 'EXTERNAL',
            result: {
                status: 'ERROR',
                healthy: false,
                message: '',
                extra: { appUrl: 'https://shop.example', statusCode: 404, info: 'Unexpected response' },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-app-url', { sync: true }), {
            props: { check },
            global: {
                stubs: {
                    'sw-settings-system-health-check-details': {
                        props: ['summary'],
                        template: '<div><p class="summary">{{ summary }}</p><slot></slot></div>',
                    },
                    'sw-settings-system-health-request': {
                        props: [
                            'url',
                            'statusCode',
                            'error',
                            'successful',
                        ],
                        template:
                            '<div class="request" :data-url="url" :data-code="statusCode" :data-error="error" :data-successful="successful"></div>',
                    },
                },
            },
        });

        expect(wrapper.find('.summary').text()).toBe('sw-settings-system-health.app-url.ERROR');
        expect(wrapper.find('.request').attributes()).toEqual(
            expect.objectContaining({
                'data-url': 'https://shop.example',
                'data-code': '404',
                'data-error': 'Unexpected response',
            }),
        );
    });
});
