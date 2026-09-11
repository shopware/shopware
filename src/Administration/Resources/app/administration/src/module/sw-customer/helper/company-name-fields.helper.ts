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
 * Mirrors CompanyAccountNameFields::areRequired(). The three settings are switchable per sales
 * channel, so a customer that belongs to one is judged by that channel's values; getValues falls
 * back to the global ones for anything the channel does not override. A shop without the account
 * type selection cannot tell a commercial customer from a private one, so the contact person stays
 * mandatory there.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function companyNamesRequired(
    systemConfigApiService?: SystemConfigApiService | null,
    salesChannelId?: string | null,
): Promise<boolean> {
    const values = await systemConfigApiService?.getValues(DOMAIN, salesChannelId ?? null).catch(() => null);

    if (!values) {
        return true;
    }

    const selectable = Boolean(values[ACCOUNT_TYPE_SELECTION]);
    const shown = values[SHOW] ?? true;
    const required = values[REQUIRED] ?? true;

    return !selectable || (Boolean(shown) && Boolean(required));
}
