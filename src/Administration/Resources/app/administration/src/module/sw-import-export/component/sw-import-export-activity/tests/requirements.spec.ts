import {
    ACTION_ABORT,
    ACTION_DOWNLOAD,
    ACTION_OPEN_PROFILE,
    ACTIVITY_ROW,
    EMPTY_STATE,
    PROFILE_MODAL,
    clickRowAction,
    createBackend,
    createLog,
    emitFromModal,
    getColumn,
    mountActivity,
    recordNotifications,
    unmountActivity,
    useFakeTimers,
    waitForReload,
} from './activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

/*
 * The activity is the list of import or export processes under the importer and the exporter.
 * It loads the import_export_log entities of its type, reloads the running ones until they finish,
 * and lets a merchant download their files, abort them, and edit the profile they used.
 */
describe('sw-import-export-activity', () => {
    afterEach(unmountActivity);

    /**
     * @requirement A merchant sees the processes of the importer or exporter with their state.
     * Imports and exports run in the background, so this list is where a merchant follows them.
     */
    it('shows the processes with their state', async () => {
        // ARRANGE
        const backend = createBackend([
            [createLog('log1', { state: 'succeeded' }), createLog('log2', { state: 'failed' })],
        ]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ASSERT
        expect(getColumn(wrapper, 'state')).toEqual([
            'sw-import-export.activity.status.succeeded',
            'sw-import-export.activity.status.failed',
        ]);
    });

    /**
     * @requirement A merchant without any process sees an empty state instead of an empty list.
     */
    it('shows an empty state when there is no process', async () => {
        // ARRANGE
        const backend = createBackend([[]]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ASSERT
        expect(wrapper.findAll(ACTIVITY_ROW)).toHaveLength(0);
        expect(wrapper.find(EMPTY_STATE).exists()).toBe(true);
    });

    /**
     * @requirement A merchant sees a running process finish without reloading the page, and is told how it ended.
     */
    it('updates a running process and tells the merchant when it finished', async () => {
        // ARRANGE
        useFakeTimers();
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1')], [createLog('log1', { state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await waitForReload();

        // ASSERT
        expect(getColumn(wrapper, 'state')).toEqual(['sw-import-export.activity.status.succeeded']);
        expect(notifications.map((notification) => notification.variant)).toEqual(['success']);
    });

    /**
     * @requirement A merchant can download the file of a process, to get the export or check what was imported.
     */
    it('downloads the file of a process', async () => {
        // ARRANGE
        const open = jest.spyOn(window, 'open').mockImplementation(() => null);
        const backend = createBackend([[createLog('log1', { activity: 'import', state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ACT
        await clickRowAction(wrapper, 0, ACTION_DOWNLOAD);

        // ASSERT
        expect(open).toHaveBeenCalledWith('https://shop.test/download/file-log1', '_blank');
    });

    /**
     * @requirement A merchant can abort a running process, for example one started with the wrong profile.
     */
    it('aborts a running process', async () => {
        // ARRANGE
        recordNotifications();
        const backend = createBackend([[createLog('log1')], [createLog('log1', { state: 'aborted' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await clickRowAction(wrapper, 0, ACTION_ABORT);

        // ASSERT
        expect(backend.importExport.cancel).toHaveBeenCalledWith('log1');
        expect(getColumn(wrapper, 'state')).toEqual(['sw-import-export.activity.status.aborted']);
    });

    /**
     * @requirement A merchant can open the profile a process used and save changes to it,
     * to fix the profile for the next run.
     */
    it('opens and saves the profile of a process', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'failed' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });
        await clickRowAction(wrapper, 0, ACTION_OPEN_PROFILE);

        // ACT
        await emitFromModal(wrapper, PROFILE_MODAL, 'profile-save');

        // ASSERT
        expect(backend.profileRepository.save).toHaveBeenCalledWith({ id: 'profileId', label: 'Default category' });
        expect(wrapper.findComponent({ name: PROFILE_MODAL }).exists()).toBe(false);
    });

    /**
     * @requirement A merchant is told when the processes cannot be loaded, instead of seeing an empty list without a reason.
     */
    it('tells the merchant when the processes cannot be loaded', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([new Error('Network error')]);

        // ACT
        await mountActivity(backend, { type: 'import' });

        // ASSERT
        expect(notifications).toEqual([{ variant: 'error', message: 'Network error' }]);
    });
});
