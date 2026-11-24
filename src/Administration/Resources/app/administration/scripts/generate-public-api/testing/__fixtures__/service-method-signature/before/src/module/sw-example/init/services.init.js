/**
 * @sw-package framework
 */
import createExampleSearchService from '../service/example-search.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('exampleSearchService', () => {
    return createExampleSearchService();
});
