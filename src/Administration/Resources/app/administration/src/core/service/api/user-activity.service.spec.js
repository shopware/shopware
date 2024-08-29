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
 * @package services-settings
 */
describe('userActivityApiService', () => {
    it('is registered correctly', async () => {
        const { userActivityApiService } = createUserActivityApiService();

        expect(userActivityApiService).toBeInstanceOf(UserActivityApiService);
    });

    it('increment frequently used correctly', async () => {
        const { userActivityApiService, clientMock } = createUserActivityApiService();

        clientMock.onPost('/_action/increment/user_activity').reply(
            200,
            {
                success: true,
            },
        );

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

        clientMock.onGet('/_action/increment/user_activity').reply(
            200,
            {
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
            },
        );

        const recentlySearch = await userActivityApiService.getIncrement({ cluster: 'id' });

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
});
