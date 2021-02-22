import PromotionSyncApiService from 'src/core/service/api/promotion-sync.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function getPromotionSyncApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new PromotionSyncApiService(client, loginService);
}

describe('core/service/api/promotion-sync.api.service', () => {
    it('is registered correctly', () => {
        expect(getPromotionSyncApiService()).toBeInstanceOf(PromotionSyncApiService);
    });

    it('should fire the necessary requests for loadPackagers & loadSorters', () => {
        const client = createHTTPClient();

        const getMethod = jest.spyOn(client, 'get').mockImplementation(() => Promise.resolve());

        const promotionSyncApiService = getPromotionSyncApiService(client, null);

        promotionSyncApiService.loadPackagers();
        expect(getMethod).toHaveBeenCalledTimes(1);
        expect(getMethod).toHaveBeenCalledWith('/_action/promotion/setgroup/packager', {
            headers: promotionSyncApiService.getBasicHeaders()
        });

        promotionSyncApiService.loadSorters();
        expect(getMethod).toHaveBeenCalledTimes(2);
        expect(getMethod).toHaveBeenCalledWith('/_action/promotion/setgroup/sorter', {
            headers: promotionSyncApiService.getBasicHeaders()
        });
    });
});
