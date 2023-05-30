/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initLanguageService() {
    Shopware.Application.addServiceProviderMiddleware('repositoryFactory', (repositoryFactory) => {
        // load the language when repositoryFactory is created
        // eslint-disable-next-line no-unused-expressions
        Shopware.Application.getContainer('service').languageAutoFetchingService;

        return repositoryFactory;
    });
}
