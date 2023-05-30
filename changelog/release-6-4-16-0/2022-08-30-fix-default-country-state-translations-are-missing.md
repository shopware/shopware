---
title: Fix default country state translations are missing
issue: NEXT-21317
---
# Core
* Added a new migration `src/Core/Migration/V6_4/Migration1661771388FixDefaultCountryStatesTranslationAreMissing.php` to add missing default language translations for country states
* Changed method `\Shopware\Core\Maintenance\System\Service\ShopConfigurator::setDefaultLanguage` to add missing default language translations for country states after changing default language
