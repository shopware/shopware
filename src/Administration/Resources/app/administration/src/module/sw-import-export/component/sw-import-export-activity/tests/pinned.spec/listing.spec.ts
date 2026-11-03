import {
    ACTIVITY_ROW,
    EMPTY_STATE,
    createBackend,
    createLog,
    getColumn,
    mountActivity,
    unmountActivity,
} from '../activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

describe('sw-import-export-activity: listing', () => {
    afterEach(unmountActivity);

    it('shows the import columns with the invalid records', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { activity: 'import' })]]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ASSERT
        const columns = wrapper.findAll(`${ACTIVITY_ROW} [data-column]`).map((cell) => cell.attributes('data-column'));
        expect(columns).toEqual([
            'createdAt',
            'profileName',
            'state',
            'records',
            'invalidRecords',
            'file.size',
        ]);
    });

    it('shows the export columns without the invalid records', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1')]]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ASSERT
        const columns = wrapper.findAll(`${ACTIVITY_ROW} [data-column]`).map((cell) => cell.attributes('data-column'));
        expect(columns).toEqual([
            'createdAt',
            'profileName',
            'state',
            'records',
            'file.size',
        ]);
    });

    it('shows the records, the invalid records, the file size and the profile', async () => {
        // ARRANGE
        const backend = createBackend([
            [
                createLog('log1', { activity: 'import', records: 7, invalidRecordsLog: { records: 3 } }),
                createLog('log2', { activity: 'import', records: 0, file: { size: 2048 } }),
            ],
        ]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ASSERT
        expect(getColumn(wrapper, 'records')).toEqual(['7', '0']);
        expect(getColumn(wrapper, 'invalidRecords')).toEqual(['3', '0']);
        expect(getColumn(wrapper, 'file.size')).toEqual(['263.00B', '2.00KB']);
        expect(getColumn(wrapper, 'profileName')).toEqual(['default_category', 'default_category']);
    });

    it('marks a dry run in the date column', async () => {
        // ARRANGE
        const backend = createBackend([
            [createLog('log1', { activity: 'dryrun' }), createLog('log2', { activity: 'import' })],
        ]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ASSERT
        const rows = wrapper.findAll(ACTIVITY_ROW);
        expect(rows[0].find('.sw-label').text()).toBe('sw-import-export.activity.dryrun');
        expect(rows[1].find('.sw-label').exists()).toBe(false);
    });

    it('shows the translated state, and marks a running one', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'unknown_state' }), createLog('log2')]]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ASSERT
        expect(getColumn(wrapper, 'state')).toEqual([
            'sw-import-export.activity.status.unknown_state',
            'sw-import-export.activity.status.progress',
        ]);
        const states = wrapper.findAll(`${ACTIVITY_ROW} [data-column="state"] span`);
        expect(states[0].classes()).not.toContain('sw-import-export-activity__progress-indicator');
        expect(states[1].classes()).toContain('sw-import-export-activity__progress-indicator');
    });

    it.each([
        [
            'import',
            'sw-import-export.activity.emptyState.titleImport sw-import-export.activity.emptyState.subLineImport',
        ],
        [
            'export',
            'sw-import-export.activity.emptyState.titleExport sw-import-export.activity.emptyState.subLineExport',
        ],
    ] as const)('shows the empty state of an %s', async (type, text) => {
        // ARRANGE
        const backend = createBackend([[]]);

        // ACT
        const wrapper = await mountActivity(backend, { type });

        // ASSERT
        expect(wrapper.find(EMPTY_STATE).text()).toBe(text);
    });
});
