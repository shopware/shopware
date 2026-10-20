/**
 * @sw-package checkout
 */
import type { CartError } from './order.types';

/**
 * @private
 */
export function getCartErrorMessage(error: CartError): string {
    if (!error.translatedMessage || error.translatedMessage === `checkout.${error.messageKey}`) {
        return error.message;
    }

    return error.translatedMessage;
}
