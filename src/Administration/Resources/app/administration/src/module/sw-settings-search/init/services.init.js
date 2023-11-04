/**
 * @package system-settings
 */
import ProductIndexService from '../service/productIndex.api.service';
import LiveSearchApiService from '../service/livesearch.api.service';
import ExcludedSearchTermService from '../../../core/service/api/excludedSearchTerm.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('productIndexService', () => {
    return new ProductIndexService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('liveSearchService', () => {
    return new LiveSearchApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('excludedSearchTermService', () => {
    return new ExcludedSearchTermService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
