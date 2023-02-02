/**
 * @package admin
 */

const HttpClient = Shopware.Classes._private.HttpFactory;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeHttpClient() {
    return HttpClient(Shopware.Context.api);
}
