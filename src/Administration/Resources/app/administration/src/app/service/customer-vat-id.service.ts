/**
 * @sw-package checkout
 */
import type RepositoryFactory from 'src/core/data/repository-factory.data';

/**
 * @private
 */
export const VAT_ID_ERROR_CODES = {
    IS_BLANK: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
    FORMAT_NOT_CORRECT: '463d3548-1caf-11eb-adc1-0242ac120002',
} as const;

/**
 * @private
 */
export type VatIdIssue = 'required' | 'format' | null;

/**
 * @private
 */
export type VatIdCountry = {
    isEu?: boolean;
    vatIdRequired?: boolean;
    checkVatIdPattern?: boolean;
    vatIdPattern?: string | null;
};

/**
 * @private
 */
export type VatIds = (string | null | undefined)[] | null | undefined;

/**
 * @private
 */
export type VatIdCustomer = {
    id: string;
    accountType?: string | null;
    vatIds?: VatIds;
};

type VatIdAddress = {
    id?: string;
    country?: VatIdCountry | null;
};

/**
 * @private
 * The parts of a customer needed to find the country its VAT ID is validated against.
 */
export type BillingCountryCustomer = {
    defaultBillingAddressId?: string | null;
    defaultBillingAddress?: VatIdAddress | null;
    addresses?: { get?: (id: string) => VatIdAddress | null | undefined } | null;
};

/**
 * @private
 */
export type EuVatIdPatterns = string[] | null;

function toRegex(pattern: string): RegExp | null {
    try {
        return new RegExp(`^(?:${pattern})$`);
    } catch {
        // Merchants can edit the patterns, so a pattern is not guaranteed to compile
        return null;
    }
}

function matches(pattern: string, vatId: string): boolean {
    return toRegex(pattern)?.test(vatId) ?? false;
}

/**
 * @private
 */
export default class CustomerVatIdService {
    private readonly repositoryFactory: RepositoryFactory;

    constructor(repositoryFactory: RepositoryFactory) {
        this.repositoryFactory = repositoryFactory;
    }

    normalizeVatIds(vatIds: VatIds): string[] {
        return (vatIds ?? []).map((vatId) => (vatId ?? '').trim()).filter((vatId) => vatId.length > 0);
    }

    isBusinessAccount(customer: Pick<VatIdCustomer, 'accountType'> | null | undefined): boolean {
        return customer?.accountType === Shopware.Constants.CUSTOMER.ACCOUNT_TYPE_BUSINESS;
    }

    getBillingCountry(customer: BillingCountryCustomer | null | undefined): VatIdCountry | null {
        if (!customer) {
            return null;
        }

        const { defaultBillingAddressId, defaultBillingAddress } = customer;

        if (defaultBillingAddress && (!defaultBillingAddressId || defaultBillingAddress.id === defaultBillingAddressId)) {
            return defaultBillingAddress.country ?? null;
        }

        if (!defaultBillingAddressId) {
            return null;
        }

        return customer.addresses?.get?.(defaultBillingAddressId)?.country ?? null;
    }

    matchesVatIdPattern(vatId: string, country: VatIdCountry | null | undefined, euPatterns: EuVatIdPatterns): boolean {
        if (!country?.vatIdPattern) {
            return true;
        }

        if (matches(country.vatIdPattern, vatId)) {
            return true;
        }

        if (!country.isEu) {
            return false;
        }

        return euPatterns === null || euPatterns.some((pattern) => matches(pattern, vatId));
    }

    /**
     * Also reports a format issue when the country does not enforce its pattern.
     */
    getVatIdIssue(
        isBusinessAccount: boolean,
        vatIds: VatIds,
        country: VatIdCountry | null | undefined,
        euPatterns: EuVatIdPatterns,
    ): VatIdIssue {
        if (!isBusinessAccount || !country) {
            return null;
        }

        const normalizedVatIds = this.normalizeVatIds(vatIds);

        if (normalizedVatIds.length === 0) {
            return country.vatIdRequired ? 'required' : null;
        }

        return normalizedVatIds.every((vatId) => this.matchesVatIdPattern(vatId, country, euPatterns)) ? null : 'format';
    }

    isBlockingVatIdIssue(issue: VatIdIssue, country: VatIdCountry | null | undefined): boolean {
        if (issue === 'required') {
            return true;
        }

        return issue === 'format' && !!country?.checkVatIdPattern;
    }

    async loadEuVatIdPatterns(): Promise<EuVatIdPatterns> {
        const criteria = new Shopware.Data.Criteria(1, 50);
        criteria.addFilter(Shopware.Data.Criteria.equals('isEu', true));

        try {
            const countries = await this.repositoryFactory.create('country').search(criteria);

            return Array.from(countries)
                .map((country) => country.vatIdPattern ?? '')
                .filter((pattern) => pattern !== '' && toRegex(pattern) !== null);
        } catch {
            return null;
        }
    }

    async getBlockingVatIdErrorCode(
        isBusinessAccount: boolean,
        vatIds: VatIds,
        country: VatIdCountry | null | undefined,
    ): Promise<string | null> {
        if (!isBusinessAccount || !country) {
            return null;
        }

        const issue = this.getVatIdIssue(isBusinessAccount, vatIds, country, await this.loadEuVatIdPatterns());

        if (!this.isBlockingVatIdIssue(issue, country)) {
            return null;
        }

        return issue === 'required' ? VAT_ID_ERROR_CODES.IS_BLANK : VAT_ID_ERROR_CODES.FORMAT_NOT_CORRECT;
    }

    /**
     * Validates the VAT IDs of a customer against its billing country before saving.
     */
    async validateVatIds(customer: VatIdCustomer, country: VatIdCountry | null | undefined): Promise<boolean> {
        const expression = `customer.${customer.id}.vatIds`;
        const errorStore = Shopware.Store.get('error');
        const errorCode = await this.getBlockingVatIdErrorCode(this.isBusinessAccount(customer), customer.vatIds, country);

        if (!errorCode) {
            errorStore.removeApiError(expression);

            return true;
        }

        errorStore.addApiError({
            expression,
            error: new Shopware.Classes.ShopwareError({ code: errorCode }),
        });

        return false;
    }
}
