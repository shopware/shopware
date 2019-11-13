const HttpClient = Shopware.Classes._private.HttpFactory;

export default function initializeHttpClient() {
    return HttpClient(Shopware.Context.api);
}
