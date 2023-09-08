---
title: Add active from to tax rule
issue: NEXT-29559
author: Marcin Kaczor
author_github: @CodeproSpace
---
# Core
* Changed in `src/Core/System/SalesChannel/Context/SalesChannelContextFactory.php` file.
  First, I check which rule has the highest (lowest) position. If there are 2 records with the same position, then the date is checked and the newer one is selected.
* Added active_from datetime column in tax_rule table `src/Core/Migration/V6_5/Migration1688927492AddTaxActiveFromField.php`
* Added function `filterByTypePosition` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Added function `highestTypePosition` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Added function `latestActivationDate` in `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleCollection.php`
* Added activeFrom in TaxRule definition `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleDefinition.php`
* Added activeFrom in TaxRule entity `src/Core/System/Tax/Aggregate/TaxRule/TaxRuleEntity.php`
* Added `AbstractTaxRuleTypeFilter` implementing `TaxRuleTypeFilterInterface`, because all filters has activeFrom matching
* Added activeFrom matching in filers: `EntireCountryRuleTypeFilter`, `IndividualStatesRuleTypeFilter`, `ZipCodeRangeRuleTypeFilter`, `ZipCodeRuleTypeFilter`

___

# Administration
* Added field to add activeFrom date time `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-settings-tax-rule-modal/sw-settings-tax-rule-modal.html.twig`
* Added activeFrom to data grid `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-tax-rule-card/index.js`
* Added activeFrom in tax rules list `src/Administration/Resources/app/administration/src/module/sw-settings-tax/component/sw-tax-rule-card/index.js`
