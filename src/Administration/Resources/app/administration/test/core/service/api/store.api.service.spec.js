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

describe('storeService', () => {
    it('is registered correctly', () => {
        expect(getStoreApiService()).toBeInstanceOf(StoreApiService);
    });

    it('handles plugin download and update with corresponding requests', async () => {
        const client = createHTTPClient();

        const getMethod = jest.spyOn(client, 'get').mockImplementation(() => Promise.resolve());
        const postMethod = jest.spyOn(client, 'post').mockImplementation(() => Promise.resolve({}));

        const storeApiService = getStoreApiService(client, null);

        await storeApiService.downloadPlugin('not-null', true);

        expect(getMethod).toHaveBeenCalledTimes(1);
        expect(postMethod).toHaveBeenCalledTimes(1);

        expect(postMethod).toHaveBeenCalledWith(
            '/_action/plugin/update',
            null,
            expect.anything()
        );
    });
});
