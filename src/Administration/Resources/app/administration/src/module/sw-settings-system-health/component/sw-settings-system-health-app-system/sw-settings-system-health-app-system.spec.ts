/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck } from '../../types';

describe('module/sw-settings-system-health/component/sw-settings-system-health-app-system', () => {
    it('should list the apps with problems first', async () => {
        const check: SystemHealthCheck = {
            name: 'AppSystem',
            category: 'EXTERNAL',
            tags: ['apps'],
            result: {
                status: 'ERROR',
                healthy: false,
                message: '',
                extra: {
                    apps: [
                        { name: 'HealthyApp', status: 'OK', reason: null, lastStatusCode: null },
                        { name: 'RejectedApp', status: 'ERROR', reason: 'credentials_rejected', lastStatusCode: 401 },
                    ],
                },
                checkedAt: '2026-11-24T10:00:00+00:00',
            },
        };

        const wrapper = mount(await wrapTestComponent('sw-settings-system-health-app-system', { sync: true }), {
            props: { check },
            global: {
                stubs: {
                    'sw-settings-system-health-check-details': { template: '<div><slot></slot></div>' },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template: '<ul><li v-for="app in dataSource">{{ app.name }}</li></ul>',
                    },
                },
            },
        });

        expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['RejectedApp', 'HealthyApp']);
    });
});
