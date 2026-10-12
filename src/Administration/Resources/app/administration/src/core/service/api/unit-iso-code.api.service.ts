/**
 * @sw-package inventory
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @private
 */
export interface UnitIsoCode {
    code: string;
    name: string;
}

/**
 * Gateway for the API end point "unit/iso-codes"
 * @class
 * @extends ApiService
 */
class UnitIsoCodeApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'unit') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'unitIsoCodeService';
    }

    getIsoCodes(): Promise<UnitIsoCode[]> {
        const headers = this.getBasicHeaders();

        return this.httpClient.get<UnitIsoCode[]>('/_action/unit/iso-codes', { headers }).then((response) => {
            return ApiService.handleResponse<UnitIsoCode[]>(response);
        });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UnitIsoCodeApiService;
