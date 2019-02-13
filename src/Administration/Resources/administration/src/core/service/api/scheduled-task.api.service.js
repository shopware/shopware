import ApiService from '../api.service';

/**
 * Gateway for the API end point "scheduled-task"
 * @class
 * @extends ApiService
 */
class ScheduledTaskApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'scheduled-task') {
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
            .post(`/_action/${this.getApiBasePath()}/run`, null, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the minimum run interval of all tasks
     *
     * @returns {Promise<T>}
     */
    getMinRunInterval() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/min-run-interval`, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default ScheduledTaskApiService;
