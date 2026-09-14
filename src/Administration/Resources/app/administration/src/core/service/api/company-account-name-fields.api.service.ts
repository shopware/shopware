import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @sw-package checkout
 */

const DOMAIN = 'core.loginRegistration';
const SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
const REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

/**
 * Mirrors CompanyAccountNameFields::areRequired() for the sales channel a customer belongs to
 */
class CompanyAccountNameFieldsApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'system-config') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'companyAccountNameFieldsService';
    }

    async isContactPersonRequired(salesChannelId?: string | null): Promise<boolean> {
        let values: Record<string, unknown>;

        try {
            const response = await this.httpClient.get(`_action/${this.apiEndpoint}`, {
                // inherit, because a sales channel that overrides neither key would read as all off otherwise
                params: { domain: DOMAIN, salesChannelId: salesChannelId ?? null, inherit: true },
                headers: this.getBasicHeaders(),
            });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const data = ApiService.handleResponse(response);
            values = Array.isArray(data) ? {} : (data as Record<string, unknown>);
        } catch {
            // the routes keep the names required while the settings cannot be read
            return true;
        }

        const shown = values[SHOW] ?? true;
        const required = values[REQUIRED] ?? true;

        return Boolean(shown) && Boolean(required);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CompanyAccountNameFieldsApiService;
