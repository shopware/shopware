/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-check-default', () => {
    it('should show the details of a check as labeled values', async () => {
        const check: SystemHealthCheck = {
            name: 'MyCheck',
            category: 'FEATURE',
            result: {
                status: 'OK',
                healthy: true,
                message: '',
                extra: { responseCode: 200, invalidCodes: ['xx', 'yy'], empty: '' },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-check-default', { sync: true }), {
            props: { check },
            global: {
                mocks: { $te: () => false },
                stubs: {
                    'sw-settings-system-health-check-details': { template: '<div><slot></slot></div>' },
                    'sw-settings-system-health-values': {
                        props: ['items'],
                        template: '<div class="values">{{ JSON.stringify(items) }}</div>',
                    },
                },
            },
        });

        expect(JSON.parse(wrapper.find('.values').text())).toEqual([
            { key: 'responseCode', label: 'Response code', value: '200' },
            { key: 'invalidCodes', label: 'Invalid codes', value: 'xx, yy' },
        ]);
    });
});
