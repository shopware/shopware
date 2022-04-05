---
title: Allow AppScript endpoints as valid target URL for ActionButtons
issue: NEXT-20202
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to persist ActionButtons even if no setup section is provided in manifest.xml.
* Changed `\Shopware\Core\Framework\App\ActionButton\AppAction` to allow null as AppSecret and relative target URLs.
* Changed `\Shopware\Core\Framework\App\ActionButton\Executor` to execute sub-requests if target URL of AppAction is relative.
