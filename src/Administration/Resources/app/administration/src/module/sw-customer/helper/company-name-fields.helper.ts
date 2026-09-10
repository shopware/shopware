/**
 * @sw-package checkout
 */

type SystemConfigApiService = {
    getValues: (domain: string, salesChannelId?: string | null) => Promise<Record<string, unknown>>;
};

const DOMAIN = 'core.loginRegistration';
const ACCOUNT_TYPE_SELECTION = 'core.loginRegistration.showAccountTypeSelection';
const SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
const REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

/**
 * Mirrors CompanyAccountNameFields::areRequired(). An Administration write carries no sales channel,
 * so the routes read the global values too. A shop without the account type selection cannot tell a
 * commercial customer from a private one, so the contact person stays mandatory there.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function companyNamesRequired(
    systemConfigApiService?: SystemConfigApiService | null,
): Promise<boolean> {
    const values = await systemConfigApiService?.getValues(DOMAIN, null).catch(() => null);

    if (!values) {
        return true;
    }

    const selectable = Boolean(values[ACCOUNT_TYPE_SELECTION]);
    const shown = values[SHOW] ?? true;
    const required = values[REQUIRED] ?? true;

    return !selectable || (Boolean(shown) && Boolean(required));
}
