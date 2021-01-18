import LiveSearchApiService from '../service/livesearch.api.service';

Shopware.Service().register('liveSearchService', () => {
    return new LiveSearchApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
