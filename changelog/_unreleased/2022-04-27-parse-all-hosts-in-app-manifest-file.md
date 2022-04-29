---
title: Parse all hosts from app manifest files
issue: NEXT-21320
---
# Core
* Added method `getAllHosts()` to `\Shopware\Core\Framework\App\Manifest\Manifest` to get all hosts referenced in the manifest file.
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to save all hosts from manifest file as allowed hosts, instead of only those that are specified in the allowed hosts field.
