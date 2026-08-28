/**
 * @sw-package checkout
 */
import { getTranslatedCartErrorMessage } from './cart-error.helper';
import type { CartError } from './order.types';

// Mimics vue-i18n `$t`: interpolates `{param}` placeholders and returns the
// snippet key unchanged when no translation exists for it.
const snippets: Record<string, string> = {
    'sw-order.cartError.promotion-not-found': 'Gutscheincode "{code}" existiert nicht.',
};

function $t(key: string, values: Record<string, unknown> = {}): string {
    const snippet = snippets[key];

    if (snippet === undefined) {
        return key;
    }

    return snippet.replace(/{(\w+)}/g, (_match, name: string) => String(values[name] ?? ''));
}

describe('src/module/sw-order/cart-error.helper', () => {
    it('translates a cart error via its messageKey and parameters', () => {
        const error = {
            level: 20,
            message: 'Promotion with code SUMMER not found!',
            messageKey: 'promotion-not-found',
            parameters: { code: 'SUMMER' },
        } as CartError;

        expect(getTranslatedCartErrorMessage(error, $t)).toBe('Gutscheincode "SUMMER" existiert nicht.');
    });

    it('falls back to the server message when no snippet exists for the messageKey', () => {
        const error = {
            level: 20,
            message: 'Some untranslated cart error',
            messageKey: 'some-unknown-key',
            parameters: {},
        } as CartError;

        expect(getTranslatedCartErrorMessage(error, $t)).toBe('Some untranslated cart error');
    });

    it('falls back to the server message when no messageKey is provided', () => {
        const error = {
            level: 20,
            message: 'Legacy error without a messageKey',
        } as CartError;

        expect(getTranslatedCartErrorMessage(error, $t)).toBe('Legacy error without a messageKey');
    });
});
