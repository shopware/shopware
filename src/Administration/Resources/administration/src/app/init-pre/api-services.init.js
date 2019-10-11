const apiServices = Shopware._private.ApiServices();

export default function initializeApiServices() {
    // Add custom api service providers
    apiServices.forEach((ApiService) => {
        const factoryContainer = this.getContainer('factory');
        const initContainer = this.getContainer('init');

        const apiServiceFactory = factoryContainer.apiService;
        const service = new ApiService(initContainer.httpClient, Shopware.Service('loginService'));
        const serviceName = service.name;
        apiServiceFactory.register(serviceName, service);

        this.addServiceProvider(serviceName, () => {
            return service;
        });
    });
}
