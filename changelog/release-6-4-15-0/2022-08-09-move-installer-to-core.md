---
title: Move installer into core
issue: NEXT-21194
---

# Core
* Added `Installer` bundle inside core, and removed `Recovery/install`
* Added new `InstallerKernel` that will be booted for the web installer
* Deprecated class `\Shopware\Core\Maintenance\System\Service\DatabaseInitializer` it will be removed in v6.5.0.0, use `SetupDatabaseAdapter` instead.
* Deprecated class `\Shopware\Recovery\Common\Service\JwtCertificateService` it will be removed in v6.5.0.0, use `JwtCertificateGenerator` instead.
___
# Next Major Version Changes
## Removed `DatabaseInitializer`

Removed class `\Shopware\Core\Maintenance\System\Service\DatabaseInitializer`, use `SetupDatabaseAdapter` instead.

## Removed `JwtCertificateService`

Removed class `\Shopware\Recovery\Common\Service\JwtCertificateService`, use `JwtCertificateGenerator` instead.
