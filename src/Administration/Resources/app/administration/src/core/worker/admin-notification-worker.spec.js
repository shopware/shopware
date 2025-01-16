import AdminNotificationWorker from 'src/core/worker/admin-notification-worker';

describe('src/core/worker/admin-notification-worker', () => {

    it('should log an error when the notification fetching fails', async () => {
        const notificationService = {
            fetchNotifications: jest.fn().mockRejectedValue(new Error('Unexpected error')),
        };
        const userConfigService = {
            upsert: jest.fn(),
        };
        const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        Shopware.Service().register('notificationsService', () => notificationService);
        Shopware.Service().register('userConfigService', () => userConfigService);

        const adminNotificationWorker = new AdminNotificationWorker();
        adminNotificationWorker.loadNotifications();

        await flushPromises();

        expect(consoleErrorSpy).toHaveBeenCalledWith('Error while fetching notifications', new Error('Unexpected error'));
    });
});
