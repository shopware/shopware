/**
 * @sw-package framework
 */

/**
 * A compact date for the details, for example "23 Nov, 13:12"; the year only when it is not the current one.
 * Uses the admin's date formatting, so locale and the user's time zone apply.
 *
 * @private
 */
export default function shortDate(value: string): string {
    const sameYear = new Date(value).getFullYear() === new Date().getFullYear();

    return Shopware.Utils.format.date(value, { month: 'short', year: sameYear ? undefined : 'numeric' });
}
