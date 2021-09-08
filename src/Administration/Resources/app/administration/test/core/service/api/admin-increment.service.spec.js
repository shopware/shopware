import AdminIncrementApiService from 'src/core/service/api/admin-increment.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAdminIncrementApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const adminIncrementApiService = new AdminIncrementApiService(client, loginService);
    return { adminIncrementApiService, clientMock };
}

describe('adminIncrementApiService', () => {
    it('is registered correctly', () => {
        const { adminIncrementApiService } = createAdminIncrementApiService();

        expect(adminIncrementApiService).toBeInstanceOf(AdminIncrementApiService);
    });

    it('increment frequently used correctly', async () => {
        const { adminIncrementApiService, clientMock } = createAdminIncrementApiService();

        clientMock.onPost('/_admin/increment/frequently-used').reply(
            200,
            {
                success: true
            }
        );

        const data = {
            key: 'sw.product.index',
            payload: { name: 'product' }
        };

        const trackActivity = await adminIncrementApiService.increment(data);

        expect(trackActivity).toEqual({
            success: true
        });
    });
});
