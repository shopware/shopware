import ApiService from '../api.service';

/**
 * Gateway for the API end point "message-queue"
 * @class
 * @extends ApiService
 * @package system-settings
 */
class MessageQueueApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'message-queue') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'messageQueueService';
    }

    /**
     * Run all due scheduled tasks
     *
     * @returns {Promise<T>}
     */
    consume(receiver, cancelToken = null) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/consume`, { receiver }, {
                headers,
                cancelToken,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageQueueApiService;
