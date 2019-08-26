const HttpClient = Shopware._private.HttpFactory;

export default function initializeHttpClient(container) {
    return HttpClient(container.contextService);
}
