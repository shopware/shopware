/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeApiServices() {
    const apiServices = Shopware._private.ApiServices();

    if (window._features_.ADMIN_VITE) {
        // Add custom api service providers
        // eslint-disable-next-line no-restricted-syntax
        for (const ApiServicePromise of apiServices) {
            // eslint-disable-next-line no-await-in-loop,@typescript-eslint/no-unsafe-assignment
            const ApiServiceRaw = await ApiServicePromise();
            // ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
            const ApiService = ApiServiceRaw.default;

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
        }

        return;
    }

    // Add custom api service providers
    apiServices.forEach((ApiService) => {
        const factoryContainer = Shopware.Application.getContainer('factory');
        const initContainer = Shopware.Application.getContainer('init');

        const apiServiceFactory = factoryContainer.apiService;
        // @ts-expect-error
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
