const HttpClient = Shopware._private.HttpFactory;

export default function initializeHttpClient() {
    const serviceContainer = Shopware.Application.getContainer('service');

    return HttpClient(serviceContainer.context);
}
