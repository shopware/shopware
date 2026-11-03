import {
    ACTION_ABORT,
    ACTION_DOWNLOAD,
    ACTION_SHOW_LOG_INFO,
    ACTION_SHOW_RESULTS,
    ACTIVITY_ROW,
    LOG_INFO_MODAL,
    RESULT_MODAL,
    clickRowAction,
    createBackend,
    createLog,
    emitFromModal,
    mountActivity,
    recordNotifications,
    unmountActivity,
} from '../activity.harness';

/**
 * @sw-package fundamentals@after-sales
 */

describe('sw-import-export-activity: actions', () => {
    afterEach(unmountActivity);

    it('offers the actions that fit a process', async () => {
        // ARRANGE
        const backend = createBackend([
            [
                createLog('log1', { result: { product: { insert: 1 } } }),
                createLog('log2', { state: 'succeeded', invalidRecordsLog: { fileId: 'invalidFileId' } }),
            ],
        ]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ASSERT
        const [running, succeeded] = wrapper.findAll(ACTIVITY_ROW);
        expect(running.find(ACTION_SHOW_RESULTS).exists()).toBe(true);
        expect(running.find(ACTION_ABORT).exists()).toBe(true);
        expect(running.findAll(ACTION_DOWNLOAD)).toHaveLength(1);
        expect(succeeded.find(ACTION_SHOW_RESULTS).exists()).toBe(false);
        expect(succeeded.find(ACTION_ABORT).exists()).toBe(false);
        expect(succeeded.findAll(ACTION_DOWNLOAD)).toHaveLength(2);
        expect(succeeded.find(ACTION_SHOW_LOG_INFO).exists()).toBe(false);
        expect(running.find(ACTION_DOWNLOAD).text()).toBe('sw-import-export.activity.contextMenu.downloadImportFile');
    });

    it('offers the log info of an export and disables its download until it succeeded', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1'), createLog('log2', { state: 'succeeded' })]]);

        // ACT
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ASSERT
        const [running, succeeded] = wrapper.findAll(ACTIVITY_ROW);
        expect(running.find(ACTION_SHOW_LOG_INFO).exists()).toBe(true);
        expect(running.find(ACTION_DOWNLOAD).attributes('disabled')).toBeDefined();
        expect(succeeded.find(ACTION_DOWNLOAD).attributes('disabled')).toBeUndefined();
        expect(running.find(ACTION_DOWNLOAD).text()).toBe('sw-import-export.activity.contextMenu.downloadExportFile');
    });

    it('downloads the invalid records of an import', async () => {
        // ARRANGE
        const open = jest.spyOn(window, 'open').mockImplementation(() => null);
        const backend = createBackend([
            [createLog('log1', { activity: 'import', state: 'failed', invalidRecordsLog: { fileId: 'invalidFileId' } })],
        ]);
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ACT
        await wrapper.findAll(ACTION_DOWNLOAD)[1].trigger('click');
        await flushPromises();

        // ASSERT
        expect(open).toHaveBeenCalledWith('https://shop.test/download/invalidFileId', '_blank');
    });

    it('does not download the invalid records of an export that did not succeed', async () => {
        // ARRANGE
        const open = jest.spyOn(window, 'open').mockImplementation(() => null);
        const backend = createBackend([
            [createLog('log1', { state: 'failed', invalidRecordsLog: { fileId: 'invalidFileId', state: 'failed' } })],
        ]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await wrapper.findAll(ACTION_DOWNLOAD)[1].trigger('click');
        await flushPromises();

        // ASSERT
        expect(backend.importExport.getDownloadUrl).not.toHaveBeenCalled();
        expect(open).not.toHaveBeenCalled();
    });

    it('opens the log info from the date and from the context menu, and closes it', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await wrapper.find('.mt-link').trigger('click');

        // ASSERT
        expect(wrapper.findComponent({ name: LOG_INFO_MODAL }).props('logEntity')).toMatchObject({ id: 'log1' });
        await emitFromModal(wrapper, LOG_INFO_MODAL, 'log-close');
        expect(wrapper.findComponent({ name: LOG_INFO_MODAL }).exists()).toBe(false);
        await clickRowAction(wrapper, 0, ACTION_SHOW_LOG_INFO);
        expect(wrapper.findComponent({ name: LOG_INFO_MODAL }).exists()).toBe(true);
    });

    it('opens the results of a process without a result object, and closes them', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'succeeded', result: { product: { insert: 1 } } })]]);
        const wrapper = await mountActivity(backend, { type: 'import' });

        // ACT
        await clickRowAction(wrapper, 0, ACTION_SHOW_RESULTS);

        // ASSERT
        const modal = wrapper.findComponent({ name: RESULT_MODAL });
        expect(modal.props('logEntity')).toMatchObject({ id: 'log1' });
        expect(modal.props('result')).toBeNull();
        await emitFromModal(wrapper, RESULT_MODAL, 'result-close');
        expect(wrapper.findComponent({ name: RESULT_MODAL }).exists()).toBe(false);
    });

    it('reloads all processes after aborting one', async () => {
        // ARRANGE
        recordNotifications();
        const backend = createBackend([[createLog('log1')]]);
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await clickRowAction(wrapper, 0, ACTION_ABORT);

        // ASSERT
        expect(backend.logRepository.search).toHaveBeenCalledTimes(2);
    });
});
