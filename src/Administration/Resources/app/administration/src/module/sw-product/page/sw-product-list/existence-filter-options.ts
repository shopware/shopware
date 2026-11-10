/**
 * @sw-package inventory
 *
 * @private
 */
export default function existenceFilterOptions(
    translate: (key: string) => string,
    property: string,
    snippetKey: string,
): {
    property: string;
    label: string;
    placeholder: string;
    optionHasCriteria: string;
    optionNoCriteria: string;
} {
    return {
        property,
        label: translate(`sw-product.filters.${snippetKey}.label`),
        placeholder: translate(`sw-product.filters.${snippetKey}.placeholder`),
        optionHasCriteria: translate(`sw-product.filters.${snippetKey}.textHasCriteria`),
        optionNoCriteria: translate(`sw-product.filters.${snippetKey}.textNoCriteria`),
    };
}
