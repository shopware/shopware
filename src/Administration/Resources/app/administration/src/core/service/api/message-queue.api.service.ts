import type { AxiosInstance, CancelToken } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Gateway for the API end point "message-queue"
 * @class
 * @extends ApiService
 * @package system-settings
 */
class MessageQueueApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'message-queue') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'messageQueueService';
    }

    /**
     * Run all due scheduled tasks
     *
     * @returns {Promise<T>}
     */
    consume(receiver: string, cancelToken ?: CancelToken): Promise<{ handledMessages: number}> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/consume`, { receiver }, {
                headers,
                cancelToken,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            }) as Promise<{ handledMessages: number}>;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageQueueApiService;
