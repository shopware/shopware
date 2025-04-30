/**
 * @sw-package framework
 */
import MessageStatsApiService from '../../../core/service/api/message-stats.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('messageStatsService', () => {
    return new MessageStatsApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
}); 