import ApiService from '../api.service';


class SystemConfigApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'system-config') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'systemConfigApiService';
    }

    checkConfig(domain, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/system-config/check', {
                params: { domain, ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getConfig(domain, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/system-config/schema', {
                params: { domain, ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getValues(domain, salesChannelId = null, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/system-config', {
                params: { domain, salesChannelId, ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    saveValues(values, salesChannelId = null, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post('_action/system-config',
                values,
                {
                    params: { salesChannelId, ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    batchSave(values, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post('_action/system-config/batch',
                values,
                {
                    params: { ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SystemConfigApiService;
