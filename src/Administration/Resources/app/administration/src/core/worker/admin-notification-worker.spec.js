/**
 * @sw-package framework
 */

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
    it('should fetch user config and set timestamp when value exists', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({
                data: {
                    'notification.lastReadAt': {
                        timestamp: '2025-12-05T11:16:44+00:00',
                    },
                },
            }),
        };
        const userService = {
            getUser: jest.fn(),
        };

        Shopware.Service().register('userConfigService', () => userConfigService);
        Shopware.Service().register('userService', () => userService);

        const adminNotificationWorker = new AdminNotificationWorker();
        await adminNotificationWorker.fetchUserConfig();

        expect(userConfigService.search).toHaveBeenCalledWith(['notification.lastReadAt']);
        expect(adminNotificationWorker._timestamp).toBe('2025-12-05T11:16:44+00:00');
        expect(userService.getUser).not.toHaveBeenCalled();
    });

    it('should fallback to user creation date when no timestamp in config', async () => {
        const userConfigService = {
            search: jest.fn().mockResolvedValue({
                data: {},
            }),
        };
        const userService = {
            getUser: jest.fn().mockResolvedValue({
                data: {
                    createdAt: {
                        timestamp: '2025-01-01T10:00:00+00:00',
                    },
                },
            }),
        };

        Shopware.Service().register('userConfigService', () => userConfigService);
        Shopware.Service().register('userService', () => userService);

        const adminNotificationWorker = new AdminNotificationWorker();
        await adminNotificationWorker.fetchUserConfig();

        expect(userConfigService.search).toHaveBeenCalledWith(['notification.lastReadAt']);
        expect(userService.getUser).toHaveBeenCalled();
        expect(adminNotificationWorker._timestamp).toBe('2025-01-01T10:00:00+00:00');
    });
});
