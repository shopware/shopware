---
title: Enforce the GARAN duration range and surface unmet label prerequisites
issue: #20105
---
# Core
* Changed `Shopware\Core\Content\Product\Garan\GaranLabelProductValidator` to also reject a `guaranteeMonths` value above 600 months (50 years). Writes with a longer duration fail with the existing `INVALID_GARAN_GUARANTEE_MONTHS` violation. Values already stored above 600 months are untouched and keep rendering their label; they only have to be corrected the next time that product is written.
___
# Administration
* Added the block `sw_product_guarantee_form_requirements_notice` to `sw-product-guarantee-form`. While "Show label in Storefront" is switched on, an `mt-banner` lists which of the label's prerequisites (a guarantee duration between 30 and 600 months in steps of 6, a manufacturer, a manufacturer product number) are still missing, resolved through variant inheritance. After a successful save the notice is scrolled into view.
* Added the event `sw-product-detail-save-success` to `Shopware.Utils.EventBus`, emitted by `sw-product-detail` once a product was saved successfully.
* Changed the snippet `global.error-codes.INVALID_GARAN_GUARANTEE_MONTHS` and `sw-product.settingsForm.helpTextGuaranteeMonths` to name the 30 to 600 months range, and added the snippets `sw-product.settingsForm.noticeGuaranteeRequirements`, `noticeGuaranteeRequirementMonths`, `noticeGuaranteeRequirementManufacturer` and `noticeGuaranteeRequirementManufacturerNumber`.
___
# Upgrade Information
## GARAN guarantee duration is capped at 600 months
`product.guaranteeMonths` accepted any positive half-year value above 24 months, so a product could carry a 500 year guarantee. Writes now also have to stay at or below 600 months.

Unlike 6.7, the guarantee duration field in the Administration deliberately does **not** receive `min`/`max` bounds: the 6.6 `sw-number-field` clamps a typed value to its bounds silently, and a guarantee duration is a commercial claim the merchant should correct knowingly. An out of range duration is reported by the API validation error and by the prerequisites notice instead.
