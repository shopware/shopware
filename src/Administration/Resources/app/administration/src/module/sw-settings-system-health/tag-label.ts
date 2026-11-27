/**
 * @sw-package framework
 */

/**
 * A tag's label from the snippet sw-settings-system-health.tag.<tag>; without one, the tag with a capital letter.
 *
 * @private
 */
export default function tagLabel(tag: string, t: (key: string) => string, te: (key: string) => boolean): string {
    const key = `sw-settings-system-health.tag.${tag}`;

    return te(key) ? t(key) : tag.charAt(0).toUpperCase() + tag.slice(1);
}
