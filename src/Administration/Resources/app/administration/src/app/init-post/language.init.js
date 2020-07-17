export default function initLanguageService() {
    const _this = this;

    Shopware.Application.addServiceProviderMiddleware('repositoryFactory', (repositoryFactory) => {
        // load the language when repositoryFactory is created
        // eslint-disable-next-line no-unused-expressions
        _this.getContainer('service').languageAutoFetchingService;

        return repositoryFactory;
    });
}
