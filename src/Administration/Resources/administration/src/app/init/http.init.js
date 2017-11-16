/* global Shopware */
import HttpClient from 'src/core/factory/http.factory';

export default function initializeHttpClient(container) {
    return HttpClient(container.contextService);
}
