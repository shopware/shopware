---
title: Add active from to tax rule
issue: NEXT-00000
---
# Core

* The biggest change is in `src/Core/System/SalesChannel/Context/SalesChannelContextFactory.php` file.
  First, I check which rule has the highest (lowest) position. If there are 2 records with the same position, then the date is checked and the newer one is selected.
* Add active_from datetime column in tax_rule table `src/Core/Migration/V6_5/Migration1688927492AddTaxActiveFromField.php`
* Add function `filterByTypePosition` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Add function `highestTypePosition` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Add function `latestActivationDate` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Add activeFrom in TaxRule definition `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleDefinition.php`
* Add activeFrom in TaxRule entity `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleEntity.php`
* Add `AbstractTaxRuleTypeFilter` implementing `TaxRuleTypeFilterInterface`, because all filters has activeFrom matching
* Add activeFrom matching in filers: `EntireCountryRuleTypeFilter`, `IndividualStatesRuleTypeFilter`, `ZipCodeRangeRuleTypeFilter`, `ZipCodeRuleTypeFilter`

# Administration
* Add field to add activeFrom date time `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-settings-tax-rule-modal/sw-settings-tax-rule-modal.html.twig`
* Add activeFrom to data grid `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-tax-rule-card/index.js`
* Display activeFrom in tax rules list `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-tax-rule-card/index.js`
