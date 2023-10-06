import ApiService from '../api.service';

/**
 * Gateway for the API end point 'user-config'
 * @class
 * @extends ApiService
 * @package system-settings
 */
class UserConfigService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_info/config-me') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userConfigService';
    }

    /**
     * @description Process search user-config based on provide array keys of user-config,
     * if keys is null, get all config of current logged-in user
     *
     * @param {Array|null} keys
     * [
     *     key_1,
     *     key_2,
     * ]
     * @returns {Object}
     * {
     *     key_1: [value1],
     *     key_2: [value2],
     * }
     */
    search(keys = null) {
        const headers = this.getBasicHeaders();
        const params = { keys };

        return this.httpClient
            .get(this.getApiBasePath(), {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                Shopware.Utils.debug.error(error);
            });
    }

    /**
     * @description Process mass upsert user-config for current logged-in user
     * @param {Array} upsertData
     * {
     *     key_1: [value1],
     *     key_2: [value2],
     * }
     */
    upsert(upsertData) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(this.getApiBasePath(), upsertData, { headers })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserConfigService;
