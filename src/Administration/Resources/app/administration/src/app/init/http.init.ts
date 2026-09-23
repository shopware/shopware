/**
 * @sw-package framework
 */
import type { HttpClient } from 'src/core/factory/http-client.types';

const HttpClient = Shopware.Classes._private.HttpFactory;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeHttpClient(): HttpClient {
    return HttpClient(Shopware.Context.api);
}
