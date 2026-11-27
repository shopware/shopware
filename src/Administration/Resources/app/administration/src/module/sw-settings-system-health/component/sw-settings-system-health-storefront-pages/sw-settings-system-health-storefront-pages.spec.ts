/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-storefront-pages', () => {
    it('should list one request per storefront page', async () => {
        const check: SystemHealthCheck = {
            name: 'SalesChannelsReadiness',
            category: 'FEATURE',
            result: {
                status: 'ERROR',
                healthy: false,
                message: '',
                extra: {
                    first: {
                        storefrontUrl: 'https://shop.example',
                        responseCode: 200,
                        responseTime: 0.1,
                        errorMessage: null,
                    },
                    second: {
                        storefrontUrl: 'https://shop.example/en',
                        responseCode: 500,
                        responseTime: 0.3,
                        errorMessage: 'Oops',
                    },
                },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-storefront-pages', { sync: true }), {
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

        expect(wrapper.findAll('.request').map((request) => request.attributes('data-code'))).toEqual(['200', '500']);
        expect(wrapper.find('.summary').text()).toBe('sw-settings-system-health.storefront-pages.summary');
    });
});
