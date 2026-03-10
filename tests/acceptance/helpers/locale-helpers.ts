/**
 * Locale-specific test data helpers
 */

export interface AddressData {
    street: string;
    city: string;
    postalCode: string;
    country: string;
}

/**
 * Get locale-specific address test data
 * @param locale - The locale code (e.g., 'de-DE', 'en-GB')
 * @returns Address data for the given locale, defaults to German address if locale not found
 */
export function getAddressDataFromLocale(locale: string): AddressData {
    const localeAddresses: Record<string, AddressData> = {
        'de-DE': { street: 'Ebbinghof 10', city: 'Schöppingen', postalCode: '48624', country: 'Germany' },
        'en-DE': { street: 'Musterstraße 123', city: 'Berlin', postalCode: '10115', country: 'Germany' },
        'en-GB': { street: '10 Downing Street', city: 'London', postalCode: 'SW1A 2AA', country: 'United Kingdom' },
        'en-US': {
            street: '1600 Pennsylvania Avenue NW',
            city: 'Washington',
            postalCode: '20500',
            country: 'United States',
        },
    };
    return localeAddresses[locale] || localeAddresses['de-DE'];
}
