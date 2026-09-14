import type { HttpCancelToken, HttpClient, HttpRequestConfig } from 'src/core/factory/http-client.types';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Gateway for the API end point "message-queue"
 * @class
 * @extends ApiService
 * @sw-package framework
 */
class MessageQueueApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'message-queue') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'messageQueueService';
    }

    /**
     * Consumes the message queue of the given receiver.
     *
     * @param receiver - Name of the transport to consume
     * @param signal - `AbortSignal` that cancels the long-running consume request.
     *   Passing a legacy `CancelToken` is deprecated and will be removed with v6.8.0.
     * @returns {Promise<T>}
     */
    consume(receiver: string, signal?: AbortSignal | HttpCancelToken): Promise<{ handledMessages: number }> {
        const config: HttpRequestConfig = {
            headers: this.getBasicHeaders(),
        };

        if (isLegacyCancelToken(signal)) {
            // @deprecated tag:v6.8.0 - Remove the CancelToken branch, only AbortSignal stays supported.
            config.cancelToken = signal;
        } else if (signal) {
            config.signal = signal;
        }

        return this.httpClient
            .post<{ handledMessages: number }>(`/_action/${this.getApiBasePath()}/consume`, { receiver }, config)
            .then(ApiService.handleResponse.bind(this));
    }
}

function isLegacyCancelToken(signal: AbortSignal | HttpCancelToken | undefined): signal is HttpCancelToken {
    return typeof signal === 'object' && signal !== null && 'throwIfRequested' in signal;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageQueueApiService;
