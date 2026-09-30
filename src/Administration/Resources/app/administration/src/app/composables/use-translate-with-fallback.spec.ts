/**
 * @sw-package framework
 */
import useTranslateWithFallback from './use-translate-with-fallback';

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
        window.Shopware = {
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
        // Resolves only when the locale arrives through TranslateOptions, mirroring vue-i18n: a
        // positional `t(key, 'en-GB')` hits the defaultMsg overload and returns the locale itself.
        t.mockImplementation((key: string, _named: unknown, options?: { locale?: string }) =>
            options?.locale === 'en-GB' ? `translated:${key}` : key,
        );
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('translated:foo.bar');
        expect(t).toHaveBeenCalledWith('foo.bar', {}, { locale: 'en-GB' });
    });

    it('returns the raw key when it exists in no locale', () => {
        te.mockReturnValue(false);
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('foo.bar');
        expect(t).not.toHaveBeenCalled();
    });

    it('does not consult the fallback locale when none is configured', () => {
        te.mockReturnValue(false);
        window.Shopware = {
            Context: { app: { fallbackLocale: '' } },
        } as unknown as typeof Shopware;
        const { tWithFallback } = useTranslateWithFallback();

        expect(tWithFallback('foo.bar')).toBe('foo.bar');
        expect(te).toHaveBeenCalledTimes(1);
        expect(te).toHaveBeenCalledWith('foo.bar');
        expect(t).not.toHaveBeenCalled();
    });
});
