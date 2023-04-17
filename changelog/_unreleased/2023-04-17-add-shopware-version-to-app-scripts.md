---
title: Add shopware version to app scripts
issue: NEXT-26010
---
# Core
* Changed `\Shopware\Core\Framework\Script\Execution\ScriptExecutor` to add `shopware.version` global variable to app scripts.
___
# Upgrade Information
## App scripts have access to shopware version

App scripts now have access to the shopware version via the `shopware.version` global variable.
```twig
{{ shopware.version }}
```
