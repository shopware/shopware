/**
 * @sw-package checkout
 */

import CustomerVatIdService, { VAT_ID_ERROR_CODES, type VatIdCountry } from './customer-vat-id.service';

const germany: VatIdCountry = {
    isEu: true,
    vatIdRequired: true,
    checkVatIdPattern: true,
    vatIdPattern: 'DE\\d{9}',
};

const switzerland: VatIdCountry = {
    isEu: false,
    vatIdRequired: false,
    checkVatIdPattern: true,
    vatIdPattern: 'CHE\\d{9}',
};

const euPatterns = [
    'ATU\\d{8}',
    'DE\\d{9}',
];

function createService(search: jest.Mock = jest.fn(() => Promise.resolve([]))) {
    const repositoryFactory = {
        create: jest.fn(() => ({ search })),
    };

    return {
        service: new CustomerVatIdService(
            repositoryFactory as unknown as ConstructorParameters<typeof CustomerVatIdService>[0],
        ),
        repositoryFactory,
        search,
    };
}

function searchReturning(patterns: (string | null)[]) {
    return jest.fn(() => Promise.resolve(patterns.map((vatIdPattern) => ({ vatIdPattern }))));
}

describe('src/app/service/customer-vat-id.service.ts', () => {
    beforeEach(() => {
        Shopware.Store.get('error').resetApiErrors();
    });

    it('drops empty VAT IDs and trims the others', () => {
        const { service } = createService();

        expect(
            service.normalizeVatIds([
                ' DE123456789 ',
                '',
                null,
                '   ',
                undefined,
            ]),
        ).toEqual(['DE123456789']);
        expect(service.normalizeVatIds(null)).toEqual([]);
    });

    it('treats only the business account type as a business', () => {
        const { service } = createService();

        expect(service.isBusinessAccount({ accountType: 'business' })).toBe(true);
        expect(service.isBusinessAccount({ accountType: 'private' })).toBe(false);
        expect(service.isBusinessAccount(null)).toBe(false);
    });

    it.each([
        [
            'the loaded default billing address',
            {
                defaultBillingAddressId: 'a',
                defaultBillingAddress: { id: 'a', country: germany },
                addresses: { get: () => ({ id: 'a', country: switzerland }) },
            },
            germany,
        ],
        [
            'a default billing address without id lookup',
            { defaultBillingAddress: { country: germany } },
            germany,
        ],
        [
            'an address made the default after loading',
            {
                defaultBillingAddressId: 'b',
                defaultBillingAddress: { id: 'a', country: germany },
                addresses: { get: (id: string) => (id === 'b' ? { id: 'b', country: switzerland } : null) },
            },
            switzerland,
        ],
        [
            'an address that is not loaded',
            {
                defaultBillingAddressId: 'c',
                defaultBillingAddress: { id: 'a', country: germany },
                addresses: { get: () => null },
            },
            null,
        ],
        [
            'no billing address',
            { defaultBillingAddressId: null, defaultBillingAddress: null },
            null,
        ],
        [
            'no customer',
            null,
            null,
        ],
    ])('resolves the billing country of %s', (_, customer, expected) => {
        const { service } = createService();

        expect(service.getBillingCountry(customer)).toBe(expected);
    });

    it.each([
        [
            'DE123456789',
            germany,
            euPatterns,
            true,
        ],
        [
            'ATU12345678',
            germany,
            euPatterns,
            true,
        ],
        [
            'DE12345',
            germany,
            euPatterns,
            false,
        ],
        [
            'ATU12345678',
            switzerland,
            euPatterns,
            false,
        ],
        [
            'anything',
            { ...germany, vatIdPattern: '' },
            euPatterns,
            true,
        ],
        [
            'anything',
            null,
            euPatterns,
            true,
        ],
        [
            'ATU12345678',
            germany,
            null,
            true,
        ],
        [
            'CHE12',
            switzerland,
            null,
            false,
        ],
    ])('matches %s against the country pattern', (vatId, country, patterns, expected) => {
        const { service } = createService();

        expect(service.matchesVatIdPattern(vatId, country, patterns)).toBe(expected);
    });

    it('does not match a pattern that does not compile', () => {
        const { service } = createService();

        expect(service.matchesVatIdPattern('DE123456789', { vatIdPattern: 'DE(\\d{9}' }, [])).toBe(false);
    });

    it.each([
        [
            'a private customer',
            false,
            [],
            germany,
            null,
        ],
        [
            'no billing country',
            true,
            [],
            null,
            null,
        ],
        [
            'a missing required VAT ID',
            true,
            [''],
            germany,
            'required',
        ],
        [
            'a missing optional VAT ID',
            true,
            [],
            { ...germany, vatIdRequired: false },
            null,
        ],
        [
            'a VAT ID of the billing country',
            true,
            ['DE123456789'],
            germany,
            null,
        ],
        [
            'a VAT ID of another member state',
            true,
            ['ATU12345678'],
            germany,
            null,
        ],
        [
            'a malformed VAT ID',
            true,
            ['DE12345'],
            germany,
            'format',
        ],
        [
            'a malformed VAT ID without format check',
            true,
            ['DE12345'],
            { ...germany, checkVatIdPattern: false },
            'format',
        ],
    ])('reports the issue of %s', (_, isBusiness, vatIds, country, expected) => {
        const { service } = createService();

        expect(service.getVatIdIssue(isBusiness, vatIds, country, euPatterns)).toBe(expected);
    });

    it('only blocks a malformed VAT ID when the country checks the format', () => {
        const { service } = createService();

        expect(service.isBlockingVatIdIssue('required', { ...germany, checkVatIdPattern: false })).toBe(true);
        expect(service.isBlockingVatIdIssue('format', germany)).toBe(true);
        expect(service.isBlockingVatIdIssue('format', { ...germany, checkVatIdPattern: false })).toBe(false);
        expect(service.isBlockingVatIdIssue(null, germany)).toBe(false);
    });

    it('loads the EU patterns and skips empty and broken ones', async () => {
        const { service, repositoryFactory, search } = createService(
            searchReturning([
                'DE\\d{9}',
                '',
                null,
                'AT(U',
            ]),
        );

        await expect(service.loadEuVatIdPatterns()).resolves.toEqual(['DE\\d{9}']);

        expect(repositoryFactory.create).toHaveBeenCalledWith('country');
        expect(search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        type: 'equals',
                        field: 'isEu',
                        value: true,
                    },
                ],
            }),
        );
    });

    it('resolves to null when the EU patterns cannot be loaded', async () => {
        const { service } = createService(jest.fn(() => Promise.reject(new Error('forbidden'))));

        await expect(service.loadEuVatIdPatterns()).resolves.toBeNull();
    });

    it('returns the error code of a blocking issue', async () => {
        const { service } = createService(searchReturning(euPatterns));

        await expect(service.getBlockingVatIdErrorCode(true, [], germany)).resolves.toBe(VAT_ID_ERROR_CODES.IS_BLANK);
        await expect(service.getBlockingVatIdErrorCode(true, ['DE12345'], germany)).resolves.toBe(
            VAT_ID_ERROR_CODES.FORMAT_NOT_CORRECT,
        );
        await expect(service.getBlockingVatIdErrorCode(true, ['ATU12345678'], germany)).resolves.toBeNull();
        await expect(
            service.getBlockingVatIdErrorCode(true, ['DE12345'], { ...germany, checkVatIdPattern: false }),
        ).resolves.toBeNull();
        await expect(service.getBlockingVatIdErrorCode(false, [], germany)).resolves.toBeNull();
    });

    it('does not block a VAT ID of an EU country when the EU patterns cannot be loaded', async () => {
        const { service } = createService(jest.fn(() => Promise.reject(new Error('forbidden'))));

        await expect(service.getBlockingVatIdErrorCode(true, ['ATU12345678'], germany)).resolves.toBeNull();
        await expect(service.getBlockingVatIdErrorCode(true, [], germany)).resolves.toBe(VAT_ID_ERROR_CODES.IS_BLANK);
        await expect(service.getBlockingVatIdErrorCode(true, ['CHE12'], switzerland)).resolves.toBe(
            VAT_ID_ERROR_CODES.FORMAT_NOT_CORRECT,
        );
    });

    it('adds the error of a blocking issue to the VAT ID field and removes it once the VAT IDs pass', async () => {
        const { service } = createService(searchReturning(euPatterns));
        const errorStore = Shopware.Store.get('error');
        const customer = { id: 'customer-id', accountType: 'business', vatIds: [''] };

        await expect(service.validateVatIds(customer, germany)).resolves.toBe(false);

        expect(errorStore.getApiErrorFromPath('customer', 'customer-id', ['vatIds'])).toEqual(
            expect.objectContaining({ code: VAT_ID_ERROR_CODES.IS_BLANK }),
        );

        await expect(service.validateVatIds({ ...customer, vatIds: ['ATU12345678'] }, germany)).resolves.toBe(true);

        expect(errorStore.getApiErrorFromPath('customer', 'customer-id', ['vatIds'])).toBeNull();
    });

    it('accepts any VAT ID of a private customer', async () => {
        const { service, search } = createService();

        await expect(
            service.validateVatIds({ id: 'customer-id', accountType: 'private', vatIds: ['DE1'] }, germany),
        ).resolves.toBe(true);

        expect(search).not.toHaveBeenCalled();
    });
});
