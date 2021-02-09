---
title: Added a webhook validator to the app system
issue: NEXT-12223
author: Maike Sestendrup
---
# Core
* Added `Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector`, to collect all hookable events and their required privileges.
* Added `Shopware\Core\Framework\Webhook\Hookable\HookableVadilator`, to validate the given webhooks and the related permissions in a `manifest.xml` file.
* Added `Shopware\Core\Framework\App\Manifest\ManifestValidator`, to validate a given `Shopware\Core\Framework\App\Manifest\Manifest`.
* Added the usage of `Shopware\Core\Framework\App\Manifest\ManifestValidator` in `Shopware\Core\Framework\App\Command\VerifyManifestCommand`.
* Changed the arguments for `Shopware\Core\Framework\App\Command\VerifyManifestCommand`. If no manifest file paths are specified, all `manifest.xml` files in the `development/custom/apps` directory are used.
