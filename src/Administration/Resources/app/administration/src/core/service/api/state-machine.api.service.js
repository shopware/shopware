/**
 * @package admin
 */

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
     * @param {string} stateFieldName (optional) Specify a different property of the base entity
     *   that holds the state id (e.g. `stateId`)
     */
    getState(
        entity,
        entityId,
        additionalParams = {},
        additionalHeaders = {},
        stateFieldName = null,
        additionalQueryParams = {},
    ) {
        const query = ApiService.makeQueryParams({
            stateFieldName,
            ...additionalQueryParams,
        });
        const route = `_action/state-machine/${entity}/${entityId}/state${query}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.get(route, {
            additionalParams,
            headers,
        });
    }

    /**
     * @param {string} stateFieldName (optional) Specify a different property of the base entity
     *   that holds the state id (e.g. `stateId`)
     */
    transitionState(
        entity,
        entityId,
        actionName,
        additionalParams = {},
        additionalHeaders = {},
        stateFieldName = null,
        additionalQueryParams = {},
    ) {
        const query = ApiService.makeQueryParams({
            stateFieldName,
            ...additionalQueryParams,
        });
        const route = `_action/state-machine/${entity}/${entityId}/state/${actionName}${query}`;

        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(
            route,
            {},
            {
                additionalParams,
                headers,
            },
        );
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default StateMachineApiService;
