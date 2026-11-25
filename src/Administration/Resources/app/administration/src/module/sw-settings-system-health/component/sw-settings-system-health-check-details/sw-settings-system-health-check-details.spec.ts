/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import SystemHealthCheckRegistry from '../../service/system-health-check-registry';
import type { SystemHealthCheck, SystemHealthStatus } from '../../types';

function check(status: SystemHealthStatus): SystemHealthCheck {
    return {
        name: 'LocalesReadiness',
        category: 'FEATURE',
        result: { status, healthy: status === 'OK', message: '', extra: {}, checkedAt: '2026-11-24T10:00:00+00:00' },
    };
}

async function createWrapper(
    status: SystemHealthStatus,
    summary: string | null = 'Two locale codes are not valid.',
    impact: string | null = null,
) {
    const registry = new SystemHealthCheckRegistry();
    registry.register('LocalesReadiness', { resolveRoute: 'sw.settings.language.index' });

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-system-health-check-details', { sync: true }), {
        props: { check: check(status), summary, impact },
        slots: { default: '<div class="evidence"></div>' },
        global: {
            provide: { systemHealthCheckRegistry: registry },
            mocks: {
                $te: (key: string) => key === 'sw-settings-system-health.resolution.LocalesReadiness.impact',
                $router: { push },
            },
            stubs: {
                'sw-settings-system-health-section': await wrapTestComponent('sw-settings-system-health-section', {
                    sync: true,
                }),
                'sw-settings-system-health-text': { props: ['text'], template: '<span>{{ text }}</span>' },
            },
        },
    });

    return { wrapper, push };
}

describe('module/sw-settings-system-health/component/sw-settings-system-health-check-details', () => {
    it('should explain a failing check and offer its resolve route', async () => {
        const { wrapper, push } = await createWrapper('WARNING');

        expect(wrapper.findAll('.sw-settings-system-health-section__title').map((title) => title.text())).toEqual([
            'sw-settings-system-health.section.what-happened',
            'sw-settings-system-health.section.what-to-do',
            'sw-settings-system-health.section.details',
        ]);
        expect(wrapper.text()).toContain('Two locale codes are not valid.');

        await wrapper.find('.mt-button').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.language.index' });
    });

    it('should only show the details of a check that passed', async () => {
        const { wrapper } = await createWrapper('OK');

        expect(wrapper.findAll('.sw-settings-system-health-section__title').map((title) => title.text())).toEqual([
            'sw-settings-system-health.section.details',
        ]);
        expect(wrapper.find('.evidence').exists()).toBe(true);
        expect(wrapper.find('.mt-button').exists()).toBe(false);
    });

    it('should put the action on "What to do" without a summary', async () => {
        const { wrapper } = await createWrapper('WARNING', null);

        const sections = wrapper.findAll('.sw-settings-system-health-section');

        expect(sections).toHaveLength(2);
        expect(sections[0].find('.mt-button').exists()).toBe(true);
    });

    it('should take a what-to-do text that depends on the result', async () => {
        const { wrapper } = await createWrapper('WARNING', null, 'These apps receive no data: Acme');

        expect(wrapper.text()).toContain('These apps receive no data: Acme');
    });
});
