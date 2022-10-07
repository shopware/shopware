---
title: Set COMPOSER_HOME var in installer if it is not set
issue: NEXT-23532
---
# Core
* Changed `\Shopware\Core\Installer\InstallerKernel` to set the ENV var `COMPOSER_HOME` if it is not already set, to prevent problems during the composer requirement checks during the installation.
