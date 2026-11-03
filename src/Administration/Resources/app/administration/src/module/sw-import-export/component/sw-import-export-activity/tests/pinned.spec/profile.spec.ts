import {
    ACTION_OPEN_PROFILE,
    PROFILE_MODAL,
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

describe('sw-import-export-activity: profile', () => {
    afterEach(unmountActivity);

    it('opens the profile of a process and closes it without saving', async () => {
        // ARRANGE
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });
        await clickRowAction(wrapper, 0, ACTION_OPEN_PROFILE);

        // ACT
        await emitFromModal(wrapper, PROFILE_MODAL, 'profile-close');

        // ASSERT
        expect(backend.profileRepository.get).toHaveBeenCalledWith('profileId');
        expect(backend.profileRepository.save).not.toHaveBeenCalled();
        expect(wrapper.findComponent({ name: PROFILE_MODAL }).exists()).toBe(false);
    });

    it('tells the merchant when a profile was saved', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        const wrapper = await mountActivity(backend, { type: 'export' });
        await clickRowAction(wrapper, 0, ACTION_OPEN_PROFILE);

        // ACT
        await emitFromModal(wrapper, PROFILE_MODAL, 'profile-save');

        // ASSERT
        expect(notifications).toEqual([{ variant: 'success', message: 'sw-import-export.profile.messageSaveSuccess' }]);
    });

    it('keeps the profile open and reports the error when saving fails', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        backend.profileRepository.save.mockImplementationOnce(() => Promise.reject(new Error('Invalid')));
        const wrapper = await mountActivity(backend, { type: 'export' });
        await clickRowAction(wrapper, 0, ACTION_OPEN_PROFILE);

        // ACT
        await emitFromModal(wrapper, PROFILE_MODAL, 'profile-save');

        // ASSERT
        expect(notifications).toEqual([{ variant: 'error', message: 'sw-import-export.profile.messageSaveError' }]);
        expect(wrapper.findComponent({ name: PROFILE_MODAL }).exists()).toBe(true);
    });

    it('reports a profile that cannot be loaded', async () => {
        // ARRANGE
        const notifications = recordNotifications();
        const backend = createBackend([[createLog('log1', { state: 'succeeded' })]]);
        backend.profileRepository.get.mockImplementationOnce(() => Promise.reject(new Error('Not found')));
        const wrapper = await mountActivity(backend, { type: 'export' });

        // ACT
        await clickRowAction(wrapper, 0, ACTION_OPEN_PROFILE);

        // ASSERT
        expect(notifications).toEqual([{ variant: 'error', message: 'Not found' }]);
        expect(wrapper.findComponent({ name: PROFILE_MODAL }).exists()).toBe(false);
    });
});
