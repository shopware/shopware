---
title: Added more app validation to the app system
issue: NEXT-12223
author: Maike Sestendrup
---
# Core
* Deprecated `Shopware\Core\Framework\App\Command\VerifyManifestCommand` - use the added `Shopware\Core\Framework\App\Command\ValidateAppCommand` instead. It validates an app by name or all apps in the `development/custom/apps` directory.
* Added the usage of `Shopware\Core\Framework\App\Command\ValidateAppCommand` in `Shopware\Core\Framework\App\Command\InstallAppCommand` and `Shopware\Core\Framework\App\Command\RefreshAppCommand`.
* Changed `Shopware\Core\Framework\App\Manifest\ManifestValidator` to `Shopware\Core\Framework\App\Manifest\Validation\ManifestValidator` and changed the parameter of the `constructor` from `ManifestValidator` to `iterable` of type `Shopware\Core\Framework\App\Manifest\Validation\ManifestValidatorInterface`.
* Changed `Shopware\Core\Framework\Webhook\Hookable\HookableValidator` to `Shopware\Core\Framework\App\Manifest\Validation\HookableValidator`.
* Added `validateTranslations()` function to `Shopware\Core\Framework\App\Manifest\Xml\Metadata` to validate the translations of property `label`.
* Added `Shopware\Core\Framework\App\Manifest\Validation\TranslationValidator` to validate translations of a `manifest.xml`.
* Added `Shopware\Core\Framework\App\Manifest\Validation\ConfigValidator` to validate a `config.xml` file of an app.
* Added `Shopware\Core\Framework\App\Manifest\Validation\AppNameValidator` to validate the app name. The app folder name and the technical name in the `manifest.xml` file must be equal.
* Changed naming of `snakeCaseToCamelCase()` function to `kebabCaseToCamelCase()` in `Shopware\Core\Framework\App\Manifest\Xml\XmlElement`.
