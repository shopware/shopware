import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * @sw-package checkout
 */

const DOMAIN = 'core.loginRegistration';
const SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
const REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

type SystemConfigReader = {
    getValues: (
        domain: string,
        salesChannelId?: string | null,
        additionalParams?: Record<string, unknown>,
    ) => Promise<Record<string, unknown>>;
};

class CompanyAccountNameFieldsApiService extends ApiService {
    private readonly systemConfigReader: SystemConfigReader | null;

    constructor(
        httpClient: HttpClient,
        loginService: LoginService,
        apiEndpoint = 'system-config',
        systemConfigReader: SystemConfigReader | null = null,
    ) {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'companyAccountNameFieldsService';
        this.systemConfigReader = systemConfigReader;
    }

    async isContactPersonRequired(salesChannelId?: string | null): Promise<boolean> {
        let values: Record<string, unknown>;

        try {
            values = await this.reader().getValues(DOMAIN, salesChannelId ?? null, { inherit: true });
        } catch {
            return true;
        }

        const shown = values[SHOW] ?? true;
        const required = values[REQUIRED] ?? true;

        return Boolean(shown) && Boolean(required);
    }

    private reader(): SystemConfigReader {
        return this.systemConfigReader ?? (Shopware.Service('systemConfigApiService') as unknown as SystemConfigReader);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CompanyAccountNameFieldsApiService;
