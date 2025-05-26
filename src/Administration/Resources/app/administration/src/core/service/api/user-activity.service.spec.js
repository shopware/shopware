import UserActivityApiService from 'src/core/service/api/user-activity.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUserActivityApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const userActivityApiService = new UserActivityApiService(client, loginService);
    return { userActivityApiService, clientMock };
}

/**
 * @sw-package fundamentals@framework
 */
describe('userActivityApiService', () => {
    it('is registered correctly', async () => {
        const { userActivityApiService } = createUserActivityApiService();

        expect(userActivityApiService).toBeInstanceOf(UserActivityApiService);
    });

    it('increment frequently used correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onPost('/_action/increment/user_activity').reply(200, {
            success: true,
        });

        const data = {
            key: 'product@sw.product.index',
            cluster: 'id',
        };

        const trackActivity = await userActivityApiService.increment(data);

        expect(trackActivity).toEqual({
            success: true,
        });
    });

    it('get frequently used correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onGet('/_action/increment/user_activity').reply(200, {
            data: [
                {
                    count: '3',
                    key: 'dashboard@sw.dashboard.index',
                },
                {
                    count: '2',
                    key: 'product@sw.product.index',
                },
            ],
        });

        const recentlySearch = await userActivityApiService.getIncrement({
            cluster: 'id',
        });

        expect(recentlySearch).toEqual({
            data: [
                {
                    count: '3',
                    key: 'dashboard@sw.dashboard.index',
                },
                {
                    count: '2',
                    key: 'product@sw.product.index',
                },
            ],
        });
    });

    it('should make a DELETE request to the correct endpoint and handle success', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: [
                'key1@example',
                'key2@example',
            ],
            cluster: 'testUserId',
        };

        clientMock.onDelete('/_action/delete-increment/user_activity', { params: paramsToDelete }).reply(204);

        const response = await userActivityApiService.deleteActivityKeys(paramsToDelete);

        expect(response.status).toBe(204);
    });

    it('should handle API errors during deletion', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();
        const paramsToDelete = {
            keys: ['key1@example'],
            cluster: 'testUserId',
        };
        const errorMessage = { message: 'Deletion failed' };

        clientMock.onDelete('/_action/delete-increment/user_activity', { params: paramsToDelete }).reply(500, errorMessage);

        try {
            await userActivityApiService.deleteActivityKeys(paramsToDelete);
        } catch (errorResponse) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(errorResponse.response.data).toEqual(errorMessage);
            // eslint-disable-next-line jest/no-conditional-expect
            expect(errorResponse.response.status).toBe(500);
        }
    });
});
