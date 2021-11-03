---
title: Unify setup scripts
issue: NEXT-17218
---
# Core
* Added `\Shopware\Core\Maintenance\Maintenance` bundle
  * Added `\Shopware\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemGenerateAppSecretCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand` instead
  * Added `\Shopware\Core\Maintenance\System\Command\SystemGenerateJwtSecretCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemGenerateJwtSecretCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemGenerateJwtSecretCommand` instead
  * Added `\Shopware\Core\Maintenance\System\Command\SystemInstallCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemInstallCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemInstallCommand` instead
  * Added `\Shopware\Core\Maintenance\System\Command\SystemSetupCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemSetupCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemSetupCommand` instead
  * Added `\Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemUpdateFinishCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand` instead
  * Added `\Shopware\Core\Maintenance\System\Command\SystemUpdatePrepareCommand`
  * Deprecated `\Shopware\Core\DevOps\System\Command\SystemUpdatePrepareCommand`, use `\Shopware\Core\Maintenance\System\Command\SystemUpdatePrepareCommand` instead
  * Added `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand`
  * Deprecated `\Shopware\Core\System\SalesChannel\Command\SalesChannelCreateCommand`, use `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand` instead
  * Added `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand`
  * Deprecated `\Shopware\Core\System\SalesChannel\Command\SalesChannelListCommand`, use `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand` instead
  * Added `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand`
  * Deprecated `\Shopware\Core\System\SalesChannel\Command\SalesChannelMaintenanceDisableCommand`, use `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand` instead
  * Added `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand`
  * Deprecated `\Shopware\Core\System\SalesChannel\Command\SalesChannelMaintenanceEnableCommand`, use `\Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand` instead
  * Added `\Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator`
  * Added `\Shopware\Core\Maintenance\System\Command\SystemConfigureShopCommand`
  * Added `\Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory`
  * Added `\Shopware\Core\Maintenance\System\Service\DatabaseInitializer`
  * Added `\Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator`
  * Added `\Shopware\Core\Maintenance\System\Service\ShopConfigurator`
  * Added `\Shopware\Core\Maintenance\User\Command\UserChangePasswordCommand`
  * Deprecated `\Shopware\Core\System\User\Command\UserChangePasswordCommand`, use `\Shopware\Core\Maintenance\User\Command\UserChangePasswordCommand` instead
  * Added `\Shopware\Core\Maintenance\User\Command\UserCreateCommand`
  * Deprecated `\Shopware\Core\System\User\Command\UserCreateCommand`, use `\Shopware\Core\Maintenance\User\Command\UserCreateCommand` instead
  * Added `\Shopware\Core\Maintenance\User\Service\UserProvisioner`
  * Deprecated `\Shopware\Core\System\User\Service\UserProvisioner`, use `\Shopware\Core\Maintenance\User\Service\UserProvisioner` instead
* Changed `\Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand` to additionally install assets from the Recovery bundle if it is present
* Added `\Shopware\Core\Framework\Plugin\Util\AssetService::copyRecoveryAssets()` to copy assets of the recovery bundle to the public folder
___
# Storefront
* Changed `\Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand` to add `snippetSetId`-parameter and to no longer ignore the `navigationCategoryId`-parameter
___
# Upgrade Information

## Added Maintenance-Bundle

A maintenance bundle was added to have one place where CLI-commands und Utils are located, that help with the ongoing maintenance of the shop.

To load enable that bundle, you should add the following line to your `/config/bundles.php` file, because from 6.5.0 onward the bundle will not be loaded automatically anymore:
```php
return [
   ...
   Shopware\Core\Maintenance\Maintenance::class => ['all' => true],
];
```
In that refactoring we moved some CLI commands into that new bundle and deprecated the old command classes. The new commands are marked as internal, as you should not rely on the PHP interface of those commands, only on the CLI API.

Additionally we've moved the `UserProvisioner` service from the `Core/System/User` namespace, to the `Core/Maintenance/User` namespace, make sure you use the service from the new location.
Before:
```php
use Shopware\Core\System\User\Service\UserProvisioner;
```
After:
```php
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
```
