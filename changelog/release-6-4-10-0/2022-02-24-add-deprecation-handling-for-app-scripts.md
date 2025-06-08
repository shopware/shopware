---
title: Add deprecation handling for App Scripts
issue: NEXT-20208
---
# Core
* Added `\Shopware\Core\Framework\Script\Debugging\ScriptTraces::addDeprecationNotice()` to capture deprecations during script execution.
* Added `\Shopware\Core\Framework\Script\Execution\DeprecatedHook` to mark a complete hook as deprecated.
* Added `\Shopware\Core\Framework\Script\Execution\Hook::getDeprecatedServices()` to mark that some services of a hook are deprecated and will be removed from the hook in the future.
* Changed `\Shopware\Core\Framework\Script\Execution\OptionalFunctionHook` to an abstract class from an interface and added `willBeRequiredInVersion()` method, to mark that a function of a hook will be required in the future.
* Added deprecation support for the script integration in the Symfony debug toolbar.
