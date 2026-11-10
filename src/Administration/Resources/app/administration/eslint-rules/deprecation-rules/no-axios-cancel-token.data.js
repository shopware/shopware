/**
 * @sw-package framework
 *
 * Texts of the `axios-v1` codemod. With 6.8 the Administration HTTP client sends direct requests with axios v1 instead
 * of axios 0.x. axios v1 deprecates `CancelToken`, `useAxiosV1: false` keeps a request on the axios 0.x client that a
 * later major removes, and the version-specific fields of the client are not part of its contract. Taken from
 * `src/core/factory/http.factory.js`, `src/core/factory/http-client.types.ts` and
 * `src/core/service/api/message-queue.api.service.ts`.
 */

const CANCEL_TOKEN =
    'axios v1 is the default HTTP client in 6.8 and deprecates CancelToken. Replace it with an AbortController ' +
    '(`signal: controller.signal`, `controller.abort()`) and detect cancellation with httpClient.isCancel(error).';

const IS_CANCEL =
    'axios v1 is the default HTTP client in 6.8. Detect cancellation with httpClient.isCancel(error); ' +
    'it covers both axios clients of the Administration.';

/** Fields of the HTTP client outside its contract → their replacement. */
const internalFields = {
    axiosV0: 'httpClient',
    axiosV1: 'httpClient',
    interceptorsV0: 'httpClient.interceptors',
    interceptorsV1: 'httpClient.interceptors',
    defaultsV0: 'httpClient.defaults',
    defaultsV1: 'httpClient.defaults',
};

module.exports = {
    id: 'axios-v1',
    description: 'Marks axios 0.x APIs of the Administration HTTP client, which sends requests with axios v1 in 6.8',
    cancelTokenMessage: CANCEL_TOKEN,
    isCancelMessage: IS_CANCEL,
    /** Import sources whose `CancelToken`, `Cancel` and `isCancel` exports are the axios 0.x ones. */
    axiosSources: ['axios', 'src/core/factory/http.factory'],
    /** Receivers of `<receiver>.isCancel()` that are axios itself rather than the HTTP client. */
    axiosObjects: ['axios', 'Axios'],
    useAxiosV0Message:
        'useAxiosV1: false keeps this request on the axios 0.x client, which a later major removes. ' +
        'Migrate the request to axios v1 and remove the option.',
    internalFields,
    internalFieldMessage: (field) =>
        `httpClient.${field} is not part of the HttpClient contract. Use ${internalFields[field]} instead; ` +
        'the HTTP client applies interceptors and defaults to both axios clients.',
    consume: {
        service: 'messageQueueService',
        method: 'consume',
        message:
            'messageQueueService.consume() only takes an axios 0.x CancelToken for cancellation; it still works with ' +
            'axios v1 in 6.8. Keep the token for this call and detect cancellation with httpClient.isCancel(error).',
    },
};
