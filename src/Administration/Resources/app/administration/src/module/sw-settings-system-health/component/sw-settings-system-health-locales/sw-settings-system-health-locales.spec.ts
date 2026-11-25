/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-locales', () => {
    it('should count the invalid locale codes and list them', async () => {
        const check: SystemHealthCheck = {
            name: 'LocalesReadiness',
            category: 'SYSTEM',
            tags: [],
            result: {
                status: 'WARNING',
                healthy: false,
                message: '',
                extra: { languageA: 'xx_XX', languageB: 'yy_YY' },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-locales', { sync: true }), {
            props: { check },
            global: {
                stubs: {
                    'sw-settings-system-health-check-details': {
                        props: ['summary'],
                        template: '<div><p class="summary">{{ summary }}</p><slot></slot></div>',
                    },
                    'sw-settings-system-health-values': {
                        props: ['items'],
                        template: '<div class="values">{{ items[0].value }}</div>',
                    },
                },
            },
        });

        expect(wrapper.find('.summary').text()).toBe('sw-settings-system-health.locales.summary');
        expect(wrapper.find('.values').text()).toBe('xx_XX, yy_YY');
    });
});
