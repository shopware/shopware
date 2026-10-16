/**
 * @sw-package inventory
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export interface UnitUneceCode {
    code: string;
    name: string;
}

/**
 * Gateway for the API end point "unit/unece-codes"
 * @class
 * @extends ApiService
 */
class UnitUneceCodeApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'unit') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'unitUneceCodeService';
    }

    getUneceCodes(): Promise<UnitUneceCode[]> {
        const headers = this.getBasicHeaders();

        return this.httpClient.get<UnitUneceCode[]>('/_action/unit/unece-codes', { headers }).then((response) => {
            return ApiService.handleResponse<UnitUneceCode[]>(response);
        });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UnitUneceCodeApiService;
