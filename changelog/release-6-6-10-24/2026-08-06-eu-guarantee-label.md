---
title: Add EU harmonised guarantee label and legal guarantee notice
issue: #19054
---
# Core
* Added `guaranteeMonths` and `guaranteeConfirmed` fields to `Shopware\Core\Content\Product\ProductDefinition` and `Shopware\Core\Content\Product\ProductEntity`. Both are `ApiAware` and `Inherited`.
* Added `Shopware\Core\Content\Product\Garan\GaranLabelResolver`, `GaranLabelRenderer`, `GaranLabelDurationFormatter` and `GaranLabelProductValidator` to render and validate the commercial guarantee (GARAN) label.
* Added `Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer` to render the statutory legal guarantee notice in all 24 official EU languages, with a fallback to English.
* Added the twig filters `sw_garan_label`, `sw_garan_label_nested`, `sw_garan_label_data_uri`, `sw_garan_label_nested_uri`, `sw_garan_label_duration`, `sw_legal_guarantee_notice` and `sw_legal_guarantee_notice_link`.
* Added the system config `core.cart.showLegalGuaranteeNotice`, which defaults to `true` and can be overridden per sales channel.
* Added `Shopware\Core\Migration\V6_6\Migration1783944800AddGaranLabel`, which adds the `guarantee_months` and `guarantee_confirmed` columns to the `product` table and updates the `order_confirmation_mail` template.
___
# API
* Added Store API route `GET /store-api/product/{productId}/garan-label` (`Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute`), returning the rendered GARAN label as SVG.
* Added Store API route `GET /store-api/legal-guarantee-notice` (`Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRoute`), returning the legal guarantee notice graphic and the link to the matching EU info page.
___
# Administration
* Added `sw-product-guarantee-form` component, rendered as a new "Guarantee (GARAN Label)" card on the product detail base view with the position identifier `sw-product-detail-base-guarantee`.
___
# Storefront
* Added the GARAN label to the buy widget on the product detail page, expandable via the new `GaranLabelToggle` plugin.
* Added the GARAN label to product line items in cart and checkout.
* Added the legal guarantee notice as a modal on the checkout confirm page, linked from the terms and conditions text.
___
# Upgrade Information
## EU harmonised guarantee labelling
### The legal guarantee notice is enabled by default
The new system config `core.cart.showLegalGuaranteeNotice` defaults to `true`. After updating, the checkout confirm page uses the new snippets `checkout.confirmTermsTextWithGuarantee` / `checkout.confirmTermsTextModalWithGuarantee` instead of `checkout.confirmTermsText` / `checkout.confirmTermsTextModal`, which additionally reference `%legalGuaranteeNoticeModalTagOpen%` and `%legalGuaranteeNoticeModalTagClose%`. If you have overridden the terms and conditions text or the block `page_checkout_confirm_tos_control_label`, adapt your override or disable the config under Settings > Shop > Cart > Checkout.

The migration also appends the notice to the `order_confirmation_mail` template. As with every mail template migration, only system default templates that have never been edited are updated (`updated_at IS NULL`), so customised templates keep their content and need the notice added manually.

### New product fields
`product.guaranteeMonths` and `product.guaranteeConfirmed` are writable via the Admin API and readable via the Store API. `guaranteeMonths` is validated on write: it must either be empty or an integer greater than 24 that is divisible by 6. The GARAN label is only rendered when `guaranteeConfirmed` is set and a guarantee duration, a manufacturer and a manufacturer number are maintained.
