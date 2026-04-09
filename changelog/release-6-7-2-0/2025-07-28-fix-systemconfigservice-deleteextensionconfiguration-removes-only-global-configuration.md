---
title: Fix SystemConfigService::deleteExtensionConfiguration removes only global configuration
issue: https://github.com/shopware/shopware/issues/11110
---
# Core
* Changed `SystemConfigService::deleteExtensionConfiguration` to remove all extension configurations keys, including SalesChannel overrides.
