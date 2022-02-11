---
title: Add translator to AppScripts
issue: NEXT-19502
---
# Core
* Changed `\Shopware\Core\Framework\Script\Execution\ScriptExecutor` to add `TranslationExtension` to the App Script twig environment, so the `|trans` filter can be used in App Scripts.
