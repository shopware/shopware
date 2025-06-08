---
title: Change column tax_rate allow three decimal
issue: NEXT-24547
---
# Core
* Added migration `Migration1676274910ChangeColumnTaxRateAllowThreeDecimal` to update data type of column `tax_rate` on table `tax` and `tax_rule`
    * Table `tax` from `DECIMAL(10, 2)` to `DECIMAL(10, 3)`
    * Table `tax_rule` from `DOUBLE(10, 2)` to `DOUBLE(10, 3)`
___
# Administration
* Added attribute `digits="3"` for templates:
  * src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-settings-tax-rule-modal/sw-settings-tax-rule-modal.html.twig
  * src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-tax-rule-card/sw-tax-rule-card.html.twig
  * src/Administration/Resources/app/administration/src/module/sw-settings-tax/page/sw-settings-tax-detail/sw-settings-tax-detail.html.twig
  * src/Administration/Resources/app/administration/src/module/sw-settings-tax/page/sw-settings-tax-list/sw-settings-tax-list.html.twig
