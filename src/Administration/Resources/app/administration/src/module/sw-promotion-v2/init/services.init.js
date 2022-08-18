import PromotionCodeApiService from '../service/promotion-code.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('promotionCodeApiService', () => {
    return new PromotionCodeApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
