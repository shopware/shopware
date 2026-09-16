/**
 * @sw-package framework
 */

import AdminNotificationWorker from 'src/core/worker/admin-notification-worker';

describe('src/core/worker/admin-notification-worker', () => {
    let notificationService;
    let userService;

    beforeAll(() => {
        notificationService = {
            fetchNotifications: jest.fn(),
        };
        userService = {
            getUser: jest.fn(),
        };
        Shopware.Service().register('notificationsService', () => notificationService);
        Shopware.Service().register('userService', () => userService);
    });

    afterEach(() => {
        jest.restoreAllMocks();
        jest.clearAllMocks();
    });

    it('should log an error when the notification fetching fails', async () => {
        notificationService.fetchNotifications.mockRejectedValue(new Error('Unexpected error'));
        const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const adminNotificationWorker = new AdminNotificationWorker();
        adminNotificationWorker.loadNotifications();

        await flushPromises();

        expect(consoleErrorSpy).toHaveBeenCalledWith('Error while fetching notifications', new Error('Unexpected error'));

        consoleErrorSpy.mockRestore();
    });

    it('should fallback to user creation date when no timestamp in config', async () => {
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
        userService.getUser.mockResolvedValue({
            data: {
                createdAt: {
                    timestamp: '2025-01-01T10:00:00+00:00',
                },
            },
        });

        const adminNotificationWorker = new AdminNotificationWorker();
        await adminNotificationWorker.fetchUserConfig();

        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith(['notification.lastReadAt']);
        expect(userService.getUser).toHaveBeenCalled();
        expect(adminNotificationWorker._timestamp).toBe('2025-01-01T10:00:00+00:00');
    });

    it.each([
        [
            'info',
            'global.default.info',
        ],
        [
            'warning',
            'global.default.warning',
        ],
        [
            'positive',
            'global.default.success',
        ],
    ])('should derive the title key "%s" -> "%s" from the notification status', (status, expectedTitle) => {
        const createNotification = jest.fn();
        jest.spyOn(Shopware.Store, 'get').mockReturnValue({ createNotification });

        const adminNotificationWorker = new AdminNotificationWorker();
        adminNotificationWorker.createNotification(status, 'a-message');

        expect(createNotification).toHaveBeenCalledWith({
            variant: status,
            message: 'a-message',
            title: expectedTitle,
        });
    });

    it('should not set a title for a status without a mapping', () => {
        const createNotification = jest.fn();
        jest.spyOn(Shopware.Store, 'get').mockReturnValue({ createNotification });

        const adminNotificationWorker = new AdminNotificationWorker();
        adminNotificationWorker.createNotification('neutral', 'a-message');

        expect(createNotification).toHaveBeenCalledWith({
            variant: 'neutral',
            message: 'a-message',
        });
    });

    it('should fetch user config and set timestamp when value exists', async () => {
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({
            data: {
                'notification.lastReadAt': {
                    timestamp: '2025-12-05T11:16:44+00:00',
                },
            },
        });

        const adminNotificationWorker = new AdminNotificationWorker();
        await adminNotificationWorker.fetchUserConfig();

        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith(['notification.lastReadAt']);
        expect(adminNotificationWorker._timestamp).toBe('2025-12-05T11:16:44+00:00');
        expect(userService.getUser).not.toHaveBeenCalled();
    });
});
