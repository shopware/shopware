const HttpClient = Shopware._private.HttpFactory;

export default function initializeHttpClient() {
    return HttpClient(Shopware.Context.get());
}
