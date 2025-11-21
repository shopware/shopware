/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeApiServices() {
    // Add custom api service providers
    const apiServicePromises = Shopware._private.ApiServices();

    // Load all API services in parallel
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const apiServiceModules = await Promise.all(
        apiServicePromises.map((ApiServicePromise) => ApiServicePromise()),
    );

    const factoryContainer = Shopware.Application.getContainer('factory');
    const initContainer = Shopware.Application.getContainer('init');
    const apiServiceFactory = factoryContainer.apiService;

    // Register all loaded services
    apiServiceModules.forEach((ApiServiceRaw) => {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const ApiService = ApiServiceRaw.default;

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
