import type { HttpCancelToken, HttpClient } from 'src/core/factory/http-client.types';
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
     *   Passing a `CancelToken` is deprecated and will be removed with v6.8.0.
     * @returns {Promise<T>}
     */
    consume(receiver: string, signal?: AbortSignal | HttpCancelToken): Promise<{ handledMessages: number }> {
        return this.httpClient
            .post<{
                handledMessages: number;
            }>(`/_action/${this.getApiBasePath()}/consume`, { receiver }, { headers: this.getBasicHeaders(), signal })
            .then(ApiService.handleResponse.bind(this));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageQueueApiService;
