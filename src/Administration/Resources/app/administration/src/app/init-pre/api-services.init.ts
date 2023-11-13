/**
 * @package admin
 */

const apiServices = Shopware._private.ApiServices();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeApiServices() {
    // Add custom api service providers
    apiServices.forEach((ApiService) => {
        const factoryContainer = Shopware.Application.getContainer('factory');
        const initContainer = Shopware.Application.getContainer('init');

        const apiServiceFactory = factoryContainer.apiService;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-assignment
        const service = new ApiService(initContainer.httpClient, Shopware.Service('loginService'));
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const serviceName = service.name as keyof ServiceContainer;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        apiServiceFactory.register(serviceName, service);

        Shopware.Application.addServiceProvider(serviceName, () => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return service;
        });
    });
}
