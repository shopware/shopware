/**
 * @sw-package framework
 */

import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export interface MessageStats {
    totalMessagesProcessed: number;
    processedSince: string;
    averageTimeInQueue: number;
    messageTypeStats: Array<{ count: number; type: string }>;
}

/**
 * Gateway for the API end point "message-stats"
 * @class
 * @extends ApiService
 */
class MessageStatsApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'message-stats') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'messageStatsService';
    }

    /**
     * Get message statistics
     *
     * @returns {Promise<MessageStats>}
     */
    getStats(): Promise<MessageStats> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<MessageStats>('/_info/message-stats.json', {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse<MessageStats>(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MessageStatsApiService;
