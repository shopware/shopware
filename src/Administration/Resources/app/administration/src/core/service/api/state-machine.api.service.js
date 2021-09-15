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

    /**
     * @param {string} stateFieldName Specify a different field name to be considered by
     *   the StateMachineActionController.
     */
    getState(entity, entityId, stateFieldName, additionalParams = {}, additionalHeaders = {}) {
        let route = `_action/state-machine/${entity}/${entityId}/state`;
        if (stateFieldName) {
            route += `?stateFieldName=${stateFieldName}`;
        }

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get(route, {
                additionalParams,
                headers,
            });
    }

    /**
     * @param {string} stateFieldName Specify a different field name to be considered by
     *   the StateMachineActionController.
     */
    transitionState(entity, entityId, actionName, stateFieldName, additionalParams = {}, additionalHeaders = {}) {
        let route = `_action/state-machine/${entity}/${entityId}/state/${actionName}`;
        if (stateFieldName) {
            route += `?stateFieldName=${stateFieldName}`;
        }

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(route, {}, {
                additionalParams,
                headers,
            });
    }
}

export default StateMachineApiService;
