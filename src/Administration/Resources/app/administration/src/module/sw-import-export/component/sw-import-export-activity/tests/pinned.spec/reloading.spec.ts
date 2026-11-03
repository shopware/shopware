import {
    createBackend,
    createLog,
    mountActivity,
    recordNotifications,
    unmountActivity,
    useFakeTimers,
    waitForReload,
} from '../activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

type SearchedCriteria = { parse: () => Record<string, unknown> };

describe('sw-import-export-activity: reloading running processes', () => {
    afterEach(unmountActivity);

    it('reloads only the running processes, with their file', async () => {
        // ARRANGE
        useFakeTimers();
        recordNotifications();
        const backend = createBackend([[createLog('log1'), createLog('log2', { state: 'succeeded' })]]);
        await mountActivity(backend, { type: 'export' });

        // ACT
        await waitForReload();

        // ASSERT
        const criteria = backend.logRepository.search.mock.calls[1][0 as never] as SearchedCriteria;
        expect(criteria.parse()).toMatchObject({
            ids: 'log1',
            filter: [{ type: 'equals', field: 'activity', value: 'export' }],
        });
    });

    it('does not notify while a process keeps running', async () => {
        // ARRANGE
        useFakeTimers();
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1')]]);
        await mountActivity(backend, { type: 'export' });

        // ACT
        await waitForReload();

        // ASSERT
        expect(backend.logRepository.search).toHaveBeenCalledTimes(2);
        expect(notifications).toEqual([]);
    });

    it('stops reloading when no process runs anymore', async () => {
        // ARRANGE
        useFakeTimers();
        recordNotifications();
        const backend = createBackend([[createLog('log1')], [createLog('log1', { state: 'succeeded' })]]);
        await mountActivity(backend, { type: 'export' });
        await waitForReload();

        // ACT
        await waitForReload();

        // ASSERT
        expect(backend.logRepository.search).toHaveBeenCalledTimes(2);
    });

    it('stops reloading when unmounted', async () => {
        // ARRANGE
        useFakeTimers();
        const backend = createBackend([[createLog('log1')]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        wrapper.unmount();
        await waitForReload();

        // ASSERT
        expect(backend.logRepository.search).toHaveBeenCalledTimes(1);
    });

    it.each([
        [
            'succeeded export',
            { activity: 'export', state: 'succeeded' },
            [
                { variant: 'success', message: 'sw-import-export.exporter.messageExportSuccess' },
            ],
        ],
        [
            'failed export',
            { activity: 'export', state: 'failed' },
            [
                { variant: 'error', message: 'sw-import-export.exporter.messageExportError' },
            ],
        ],
        [
            'failed import with invalid records',
            { activity: 'import', state: 'failed', invalidRecordsLog: { id: 'x' } },
            [
                { variant: 'error', message: 'sw-import-export.importer.messageImportError' },
            ],
        ],
        [
            'succeeded dry run',
            { activity: 'dryrun', state: 'succeeded' },
            [
                { variant: 'success', message: 'sw-import-export.importer.messageImportSuccess' },
            ],
        ],
        [
            'succeeded import without records',
            { activity: 'import', state: 'succeeded', records: 0 },
            [
                { variant: 'success', message: 'sw-import-export.importer.messageImportSuccess' },
                { variant: 'warning', message: 'sw-import-export.importer.messageImportWarning' },
            ],
        ],
        ['aborted export', { activity: 'export', state: 'aborted' }, [{ variant: 'error', message: '' }]],
    ])('notifies about a %s', async (_, finished, expected) => {
        // ARRANGE
        useFakeTimers();
        const notifications = recordNotifications();
        const backend = createBackend([
            [createLog('log1', { activity: finished.activity })],
            [createLog('log1', finished)],
        ]);
        await mountActivity(backend);

        // ACT
        await waitForReload();

        // ASSERT
        expect(notifications).toEqual(expected);
    });

    it('reports a reload whose result is no entity collection, or that fails', async () => {
        // ARRANGE
        useFakeTimers();
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1')], { total: 0 }, new Error('Network error')]);
        await mountActivity(backend, { type: 'export' });

        // ACT
        await waitForReload();
        await waitForReload();

        // ASSERT
        expect(notifications).toEqual([
            { variant: 'error', message: 'global.notification.notificationLoadingDataErrorMessage' },
            { variant: 'error', message: 'Network error' },
        ]);
    });
});
