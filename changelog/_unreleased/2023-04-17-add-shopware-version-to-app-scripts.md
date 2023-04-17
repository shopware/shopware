---
title: Add shopware version to app scripts
issue: NEXT-26010
---
# Core
* Changed `\Shopware\Core\Framework\Script\Execution\ScriptExecutor` to add `shopware.version` global variable to app scripts.
* Changed `\Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension` to add `version_compare` function to app scripts.
___
# Upgrade Information
## App scripts have access to shopware version

App scripts now have access to the shopware version via the `shopware.version` global variable.
```twig
{% if version_compare('6.4', shopware.version, '<=') %}
    {# 6.4 or lower compatible code #}
{% else %}
    {# 6.5 or higher compatible code #}    
{% endif %}
```
