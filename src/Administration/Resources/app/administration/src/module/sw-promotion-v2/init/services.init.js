import PromotionCodeApiService from '../service/promotion-code.api.service';

Shopware.Service().register('promotionCodeApiService', () => {
    return new PromotionCodeApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
