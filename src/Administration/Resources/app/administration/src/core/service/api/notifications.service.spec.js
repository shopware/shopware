/**
 * @package admin
 */
import NotificationsService from 'src/core/service/api/notifications.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createNotificationsService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const notificationsService = new NotificationsService(client, loginService);
    return { notificationsService, clientMock };
}

const exampleNotification = {
    notifications: [
        {
            status: 'success',
            message: 'This is a successful message',
        },
        {
            status: 'error',
            message: 'This is an error message',
        },
    ],
    timestamp: null,
};

describe('notificationsService', () => {
    it('fetches notifications correctly', async () => {
        const { notificationsService, clientMock } = createNotificationsService();

        clientMock.onGet('/notification/message').reply(
            200,
            [exampleNotification],
        );

        const notifications = await notificationsService.fetchNotifications(2);

        expect(notifications).toEqual([exampleNotification]);
    });
});
