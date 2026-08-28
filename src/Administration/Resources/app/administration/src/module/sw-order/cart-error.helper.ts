/**
 * @sw-package checkout
 */
import type { CartError } from './order.types';

/**
 * @private
 *
 * Translates a cart error into the current Administration language.
 *
 * Cart errors returned by the API carry a hardcoded English `message` alongside a
 * stable `messageKey` and `parameters`. We look up a localized snippet for that
 * `messageKey` (mirroring how the Storefront renders cart errors) and fall back to
 * the server-provided `message` when no matching snippet exists.
 */
export function getTranslatedCartErrorMessage(
    error: CartError,
    $t: (key: string, values?: Record<string, unknown>) => unknown,
): string {
    if (!error.messageKey) {
        return error.message;
    }

    const snippetKey = `sw-order.cartError.${error.messageKey}`;
    const translatedMessage = String($t(snippetKey, error.parameters ?? {}));

    return translatedMessage === snippetKey ? error.message : translatedMessage;
}
