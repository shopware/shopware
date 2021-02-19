import ProductIndexService from '../service/productIndex.api.service';
import LiveSearchApiService from '../service/livesearch.api.service';

Shopware.Service().register('productIndexService', () => {
    return new ProductIndexService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Shopware.Service().register('liveSearchService', () => {
    return new LiveSearchApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
