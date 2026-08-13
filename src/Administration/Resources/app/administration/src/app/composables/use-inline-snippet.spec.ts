import { useInlineSnippet } from './use-inline-snippet';

function stubShopware(currentLocale: string, fallbackLocale: string): void {
    window.Shopware = {
        Store: { get: jest.fn().mockReturnValue({ currentLocale }) },
        Context: { app: { fallbackLocale } },
        Utils: {
            types: {
                isEmpty: (value: unknown) => value === null || value === undefined || Object.keys(value).length === 0,
                isObject: (value: unknown) => typeof value === 'object' && value !== null,
            },
        },
    } as unknown as typeof Shopware;
}

describe('src/app/composables/use-inline-snippet', () => {
    it('returns the value for the current locale when present', () => {
        stubShopware('de-DE', 'en-GB');
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'de-DE': 'Hallo', 'en-GB': 'Hello' })).toBe('Hallo');
    });

    it('falls back to the fallback locale when the current locale is missing', () => {
        stubShopware('de-DE', 'en-GB');
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'en-GB': 'Hello' })).toBe('Hello');
    });

    it('returns the first non-empty value when neither locale matches', () => {
        stubShopware('de-DE', 'en-GB');
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'fr-FR': '', 'it-IT': 'Ciao' })).toBe('Ciao');
    });

    it('returns an empty string for an empty value', () => {
        stubShopware('de-DE', 'en-GB');
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({})).toBe('');
    });
});
