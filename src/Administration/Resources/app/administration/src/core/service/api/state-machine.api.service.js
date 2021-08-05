import ApiService from '../api.service';

/**
 * Gateway for the API end point "state-machine"
 * @class
 * @extends ApiService
 */
class StateMachineApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'state-machine') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'stateMachineService';
    }

    getState(entity, entityId, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/state-machine/${entity}/${entityId}/state`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(route, {
                additionalParams,
                headers,
            });
    }

    transitionState(entity, entityId, actionName, additionalParams = {}, additionalHeaders = {}) {
        const route = `_action/state-machine/${entity}/${entityId}/state/${actionName}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, {}, {
                additionalParams,
                headers,
            });
    }
}

export default StateMachineApiService;
