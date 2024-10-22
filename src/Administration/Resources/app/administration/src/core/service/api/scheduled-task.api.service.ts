/**
 * @package admin
 */

import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Gateway for the API end point "scheduled-task"
 * @class
 * @extends ApiService
 */
class ScheduledTaskApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'scheduled-task') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'scheduledTaskService';
    }

    /**
     * Run all due scheduled tasks
     *
     * @returns {Promise<T>}
     */
    runTasks() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post<unknown>(`/_action/${this.getApiBasePath()}/run`, null, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the minimum run interval of all tasks
     *
     * @returns {Promise<T>}
     */
    getMinRunInterval(): Promise<{ minRunInterval: number }> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get<{
                minRunInterval: number;
            }>(`/_action/${this.getApiBasePath()}/min-run-interval`, { headers })
            .then(ApiService.handleResponse.bind(this));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ScheduledTaskApiService;
