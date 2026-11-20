/**
 * @sw-package framework
 *
 * Administration snippet keys that are gone in 6.8. The keys of `global.sw-condition.condition.*` are deprecated
 * since 6.7.0.0 and `sw-import-export.profile.addNewProfileTooltipLanguage` is deprecated as well; the other keys
 * were removed in 6.7.17.0 already. `…cartTaxDisplay.label`, `…lineItemOfTypeRule.label` and `…dayOfWeekRule` are
 * left out, because the core still uses them.
 */

module.exports = {
    // Old key → new key; every new key exists from 6.7.17.0 on
    renamed: {
        'sw-settings-customer-group.detail.fieldDisplayGrossLabel': 'sw-settings-customer-group.detail.taxDisplay.label',
        'sw-ui-shell-update-2026-modal.pages.darkMode.profileSettingsLink': 'sw-ui-shell-update-2026-modal.profileSettingsLink',
        'global.sw-condition.condition.cartTaxDisplay.gross': 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross',
        'global.sw-condition.condition.cartTaxDisplay.net': 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net',
        'global.sw-condition.condition.lineItemOfTypeRule.product': 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.product',
        'global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge': 'global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion',
        'global.sw-condition.condition.promotionCodeOfTypeRule.global': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.global',
        'global.sw-condition.condition.promotionCodeOfTypeRule.fixed': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.fixed',
        'global.sw-condition.condition.promotionCodeOfTypeRule.individual': 'global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.individual',
    },

    // Keys without replacement
    removed: [
        'sw-app.component.sw-app-wrong-app-url-modal.explanationHeader',
        'sw-app.component.sw-app-wrong-app-url-modal.textDetails',
        'sw-import-export.profile.addNewProfileTooltipLanguage',
    ],
};
