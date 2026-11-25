/**
 * Resolve an explicit Administration snippet reference while retaining the authored fallback.
 *
 * @private
 * @sw-package discovery
 */
export function translateContentSystemText(
    key: unknown,
    fallback: string,
    exists: (key: string) => boolean,
    translate: (key: string) => string,
): string {
    if (typeof key !== 'string' || key.length === 0 || !exists(key)) {
        return fallback;
    }

    const translated = translate(key);

    return translated || fallback;
}
