/**
 * @sw-package checkout
 */

const DOMAIN = 'core.loginRegistration';
const SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
const REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

type SystemConfigReader = {
    getValues(
        domain: string,
        salesChannelId?: string | null,
        additionalParams?: Record<string, unknown>,
    ): Promise<Record<string, unknown>>;
};

/**
 * @private
 */
export default class CompanyAccountNameFieldsService {
    constructor(private readonly systemConfigApiService: SystemConfigReader) {}

    async isContactPersonRequired(salesChannelId?: string | null): Promise<boolean> {
        let values: Record<string, unknown>;

        try {
            values = await this.systemConfigApiService.getValues(DOMAIN, salesChannelId ?? null, { inherit: true });
        } catch {
            return true;
        }

        const shown = values[SHOW] ?? true;
        const required = values[REQUIRED] ?? true;

        return Boolean(shown) && Boolean(required);
    }
}
