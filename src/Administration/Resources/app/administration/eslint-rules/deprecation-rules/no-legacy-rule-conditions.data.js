/**
 * @sw-package framework
 *
 * The rule builder condition components that 6.8 removes, taken from the `@deprecated tag:v6.8.0` markers. The core
 * renders their conditions with `sw-condition-generic-line-item`, driven by `Rule::getConfig()`. Since 6.7.13.0 the
 * purchase price condition stores `type` (`gross` or `net`) instead of `isNet`.
 */

const GENERIC = 'Use sw-condition-generic-line-item for line item conditions or sw-condition-generic otherwise, and configure the fields in Rule::getConfig().';

module.exports = {
    id: 'rule-condition-components',
    description: 'Marks the legacy rule condition components, which 6.8 replaces with the generic condition components',
    components: {
        'sw-condition-line-item-in-category': `sw-condition-line-item-in-category is removed. ${GENERIC}`,
        'sw-condition-line-item-purchase-price': 'sw-condition-line-item-purchase-price is removed. Use sw-condition-generic-line-item and configure the rule in Rule::getConfig() (field type, not isNet).',
        'sw-condition-is-net-select': `sw-condition-is-net-select is removed. The purchase price condition stores type ('gross' or 'net') instead of isNet. ${GENERIC}`,
    },
};
