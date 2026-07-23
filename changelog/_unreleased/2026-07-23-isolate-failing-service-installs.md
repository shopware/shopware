---
title: Isolate failing service installs so one invalid manifest does not block the rest
issue: #18621
author: Max Stegmeyer
author_github: @mstegmeyer
---
# Core
* Changed `\Shopware\Core\Service\ServiceLifecycle::install()` and `::update()` to catch `AppXmlParsingException` while building the manifest, so a service with an invalid `manifest.xml` is logged and skipped instead of throwing.
* Changed `\Shopware\Core\Service\AllServiceInstaller::install()` to isolate each service installation in its own `try`/`catch`, so a single failing service can no longer abort the installation of the remaining services during `bin/console services:install`.
