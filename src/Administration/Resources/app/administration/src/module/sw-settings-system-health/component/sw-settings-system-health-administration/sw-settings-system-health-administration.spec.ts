/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-administration', () => {
    it('should name the missing build files as the error of the start page request', async () => {
        const check: SystemHealthCheck = {
            name: 'AdministrationReadiness',
            category: 'FEATURE',
            tags: [],
            result: {
                status: 'FAILURE',
                healthy: false,
                message: '',
                extra: { missingArtifactsForJsBundles: ['Storefront'], indexResponseTime: 0.2 },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-administration', { sync: true }), {
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

        expect(wrapper.find('.summary').text()).toBe('sw-settings-system-health.administration.missing');
        expect(wrapper.find('.request').attributes()).toEqual(
            expect.objectContaining({
                'data-error': 'sw-settings-system-health.administration.missing',
                'data-successful': 'false',
            }),
        );
    });
});
