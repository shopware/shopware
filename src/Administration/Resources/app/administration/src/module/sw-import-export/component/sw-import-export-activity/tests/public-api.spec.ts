import { createBackend, createLog, getColumn, mountActivity, unmountActivity } from './activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

describe('sw-import-export-activity', () => {
    afterEach(unmountActivity);

    /** @public-api addActivity */
    it('adds a process at the top of the list', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        wrapper.vm.addActivity(createLog('log2', { state: 'failed' }));
        await flushPromises();

        // ASSERT
        expect(getColumn(wrapper, 'state')).toEqual([
            'sw-import-export.activity.status.failed',
            'sw-import-export.activity.status.succeeded',
        ]);
    });

    /** @public-api dateFilter */
    it('returns the date filter', async () => {
        // ARRANGE
        const wrapper = await mountActivity(createBackend());

        // ACT
        const dateFilter = wrapper.vm.dateFilter as (value: string) => string;

        // ASSERT
        expect(dateFilter('')).toBe('');
    });
});
