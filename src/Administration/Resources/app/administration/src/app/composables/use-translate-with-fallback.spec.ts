import { useTranslateWithFallback } from './use-translate-with-fallback';

const t = jest.fn();
const te = jest.fn();

jest.mock('vue-i18n', () => ({
    useI18n: () => ({ t, te }),
}));

describe('src/app/composables/use-translate-with-fallback', () => {
    beforeEach(() => {
        t.mockReset();
        te.mockReset();
        t.mockImplementation((key: string) => `translated:${key}`);
        global.Shopware = {
            Context: { app: { fallbackLocale: 'en-GB' } },
        } as unknown as typeof Shopware;
    });

    it('returns an empty string for a falsy key', () => {
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('')).toBe('');
        expect(t).not.toHaveBeenCalled();
    });

    it('translates with the active locale when the key exists there', () => {
        te.mockReturnValue(true);
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('translated:foo.bar');
        expect(t).toHaveBeenCalledWith('foo.bar');
    });

    it('falls back to the fallback locale when the key only exists there', () => {
        te.mockImplementation((_key: string, locale?: string) => locale === 'en-GB');
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('translated:foo.bar');
        expect(t).toHaveBeenCalledWith('foo.bar', 'en-GB');
    });

    it('returns the raw key when it exists in no locale', () => {
        te.mockReturnValue(false);
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('foo.bar');
        expect(t).not.toHaveBeenCalled();
    });
});
