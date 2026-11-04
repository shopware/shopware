/**
 * @sw-package inventory
 */

/** @private */
export { describeSaveError, describeSeoUrlErrors } from '../private';

/**
 * A translator that knows the given snippets and, like vue-i18n, returns the key for any other.
 *
 * @private
 */
export function createTranslate(snippets: Record<string, string> = {}) {
    return (key: string, values: Record<string, unknown> = {}) =>
        Object.entries(values).reduce(
            (text, [name, value]) => text.replace(`{${name}}`, String(value)),
            snippets[key] ?? key,
        );
}

/**
 * A failed request with the errors the API returned.
 *
 * @private
 */
export function createFailedRequest(errors: Record<string, unknown>[]) {
    return { response: { data: { errors } } };
}
