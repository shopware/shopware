/**
 * @sw-package discovery
 */

/**
 * @private
 */
export function getEffectivePriceBasis(
    customerGroup: { displayGross?: boolean | null; priceBasis?: string | null } | null | undefined,
): 'gross' | 'net' {
    if (customerGroup?.priceBasis === 'gross' || customerGroup?.priceBasis === 'net') {
        return customerGroup.priceBasis;
    }

    return customerGroup?.displayGross ? 'gross' : 'net';
}
