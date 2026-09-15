import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @sw-package checkout
 */

type ContactPersonRequirement = {
    contactPersonRequired: boolean;
};

class CompanyAccountNameFieldsApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'customer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'companyAccountNameFieldsService';
    }

    isContactPersonRequired(salesChannelId?: string | null): Promise<boolean> {
        return this.httpClient
            .get<ContactPersonRequirement>(`/_action/${this.getApiBasePath()}/company-account-name-fields`, {
                params: salesChannelId ? { salesChannelId } : {},
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response))
            .then((data) => Boolean((data as ContactPersonRequirement).contactPersonRequired))
            .catch(() => true);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CompanyAccountNameFieldsApiService;
