import StoreApiService from 'src/core/service/api/store.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function getStoreApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new StoreApiService(client, loginService);
}

/**
 * @package services-settings
 */
describe('storeService', () => {
    it('is registered correctly', async () => {
        expect(getStoreApiService()).toBeInstanceOf(StoreApiService);
    });
});
