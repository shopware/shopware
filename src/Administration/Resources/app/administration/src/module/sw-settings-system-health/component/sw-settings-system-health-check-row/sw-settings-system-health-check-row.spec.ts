/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import SystemHealthCheckRegistry from '../../service/system-health-check-registry';
import type { SystemHealthCheck } from '../../types';

async function createWrapper(check: SystemHealthCheck) {
    const registry = new SystemHealthCheckRegistry();
    registry.register('AppUrlVerification', { component: 'sw-settings-system-health-app-url' });

    return mount(await wrapTestComponent('sw-settings-system-health-check-row', { sync: true }), {
        props: { check, isOpen: true },
        global: {
            provide: { systemHealthCheckRegistry: registry },
            stubs: {
                'sw-settings-system-health-app-url': { template: '<div class="own-view"></div>' },
                'sw-settings-system-health-check-default': { template: '<div class="default-view"></div>' },
                'sw-settings-system-health-section': await wrapTestComponent('sw-settings-system-health-section', {
                    sync: true,
                }),
                'sw-context-button': true,
                'sw-context-menu-item': true,
            },
        },
    });
}

const result = {
    status: 'OK' as const,
    healthy: true,
    message: 'APP_URL points to this shop',
    extra: { appUrl: 'https://shop.example' },
    checkedAt: '2026-11-24T10:00:00+00:00',
};

describe('module/sw-settings-system-health/component/sw-settings-system-health-check-row', () => {
    it('should show the registered component of the check', async () => {
        const wrapper = await createWrapper({ name: 'AppUrlVerification', category: 'EXTERNAL', tags: [], result });

        expect(wrapper.find('.own-view').exists()).toBe(true);
        expect(wrapper.find('.default-view').exists()).toBe(false);
    });

    it('should show the default view for a check without its own component', async () => {
        const wrapper = await createWrapper({ name: 'MyCheck', category: 'EXTERNAL', tags: [], result });

        expect(wrapper.find('.default-view').exists()).toBe(true);
    });

    it('should offer to run a check that never ran', async () => {
        const wrapper = await createWrapper({ name: 'MyCheck', category: 'EXTERNAL', tags: [], result: null });

        await wrapper.find('.sw-settings-system-health-check-row__panel .mt-button').trigger('click');

        expect(wrapper.emitted('run')).toEqual([['MyCheck']]);
    });

    it('should name the tags of the check', async () => {
        const wrapper = await createWrapper({ name: 'AppUrlVerification', category: 'EXTERNAL', tags: ['apps'], result });

        expect(wrapper.find('.sw-settings-system-health-check-row__meta').text()).toContain(
            'sw-settings-system-health.check-row.tags',
        );
    });
});
