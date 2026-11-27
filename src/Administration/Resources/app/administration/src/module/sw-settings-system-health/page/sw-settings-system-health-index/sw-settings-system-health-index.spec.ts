/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { SystemHealthCheck, SystemHealthResult } from '../../types';

function check(name: string, category: string, result: Partial<SystemHealthResult> | null): SystemHealthCheck {
    return {
        name,
        category,
        result:
            result === null
                ? null
                : { status: 'OK', healthy: true, message: '', extra: {}, checkedAt: '2026-11-24T10:00:00+00:00', ...result },
    };
}

async function createWrapper(getChecks: jest.Mock, run = jest.fn(() => Promise.resolve())) {
    const wrapper = mount(await wrapTestComponent('sw-settings-system-health-index', { sync: true }), {
        global: {
            provide: { systemHealthApiService: { getChecks, run } },
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': {
                    template: '<div><div class="card-header"><slot name="headerRight"></slot></div><slot></slot></div>',
                },
                'sw-settings-system-health-check-row': {
                    props: ['check', 'isRunning'],
                    emits: ['run'],
                    template:
                        '<button class="check-row" :data-name="check.name" :data-status="check.result?.status" :data-running="isRunning" @click="$emit(\'run\', check.name)"></button>',
                },
            },
        },
    });
    await flushPromises();

    return { wrapper, run };
}

describe('module/sw-settings-system-health/page/sw-settings-system-health-index', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('should group the checks and only open the ones that need attention', async () => {
        const { wrapper } = await createWrapper(
            jest.fn(() =>
                Promise.resolve([
                    check('AppUrlVerification', 'EXTERNAL', { status: 'ERROR', healthy: false }),
                    check('LocalesReadiness', 'FEATURE', {}),
                    check('SalesChannelsReadiness', 'FEATURE', null),
                ]),
            ),
        );

        expect(wrapper.findAll('.sw-settings-system-health-index__group-header').map((header) => header.text())).toEqual([
            'sw-settings-system-health.group.attention 1',
            'sw-settings-system-health.group.passed 1',
            'sw-settings-system-health.group.not-run 1',
        ]);
        expect(wrapper.findAll('.check-row').map((row) => row.attributes('data-name'))).toEqual(['AppUrlVerification']);
    });

    it('should show a check as running until its result is newer', async () => {
        const failing = check('AppUrlVerification', 'EXTERNAL', { status: 'ERROR', healthy: false });
        const getChecks = jest
            .fn()
            .mockResolvedValueOnce([failing])
            .mockResolvedValueOnce([failing])
            .mockResolvedValueOnce([check('AppUrlVerification', 'EXTERNAL', { checkedAt: '2026-11-24T11:00:00+00:00' })]);
        const { wrapper, run } = await createWrapper(getChecks);

        await wrapper.find('.check-row').trigger('click');
        await flushPromises();

        expect(run).toHaveBeenCalledWith('AppUrlVerification');
        expect(wrapper.find('.check-row').attributes('data-running')).toBe('true');

        jest.advanceTimersByTime(2000);
        await flushPromises();
        expect(wrapper.find('.check-row').attributes('data-running')).toBe('true');

        jest.advanceTimersByTime(2000);
        await flushPromises();
        expect(getChecks).toHaveBeenCalledTimes(3);

        // the new result passed, so the check moved to the closed "Passed" group
        await wrapper.findAll('.sw-settings-system-health-index__group-header')[1].trigger('click');
        expect(wrapper.find('.check-row').attributes()).toEqual(
            expect.objectContaining({ 'data-status': 'OK', 'data-running': 'false' }),
        );
    });

    it('should run all checks with one request', async () => {
        const { wrapper, run } = await createWrapper(
            jest.fn(() => Promise.resolve([check('AppUrlVerification', 'EXTERNAL', { status: 'ERROR', healthy: false })])),
        );

        await wrapper.find('.card-header .mt-button').trigger('click');
        await flushPromises();

        expect(run).toHaveBeenCalledTimes(1);
        expect(run).toHaveBeenCalledWith(null);
        expect(wrapper.find('.check-row').attributes('data-running')).toBe('true');
    });
});
