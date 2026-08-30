/**
 * @sw-package checkout
 */
import { getCartErrorMessage } from './cart-error.helper';
import type { CartError } from './order.types';

describe('src/module/sw-order/cart-error.helper', () => {
    it('uses the server translated message', () => {
        const error = {
            level: 20,
            message: 'Promotion with code SUMMER not found!',
            messageKey: 'promotion-not-found',
            translatedMessage: 'Gutscheincode "SUMMER" existiert nicht.',
        } as CartError;

        expect(getCartErrorMessage(error)).toBe('Gutscheincode "SUMMER" existiert nicht.');
    });

    it('falls back when the translation resolved to the snippet key', () => {
        const error = {
            level: 20,
            message: 'Something went wrong',
            messageKey: 'custom-plugin-error',
            translatedMessage: 'checkout.custom-plugin-error',
        } as CartError;

        expect(getCartErrorMessage(error)).toBe('Something went wrong');
    });

    it('falls back when no translated message is present', () => {
        const error = {
            level: 20,
            message: 'Legacy error without a translation',
            messageKey: 'legacy-error',
        } as CartError;

        expect(getCartErrorMessage(error)).toBe('Legacy error without a translation');
    });
});
