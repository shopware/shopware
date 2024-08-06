---
title: Enhance plugin:list command output with information about plugins required by composer
issue: NEXT-34309
---
# Core
* Added `\Composer\Autoload\ClassLoader` service to the DI container.
* Changed `GetClassesPerAreaCommand` to receive `ClassLoader` through DI.
* Changed `PluginListCommand` to include information about plugins loaded by `ComposerPluginLoader`.
* Changed `ComposerPluginLoaderTest` to have pass proper number of arguments to `ComposerPluginLoader::__construct`.
* Changed `ComposerPluginLoaderTest` and `PluginListCommandTest` and corresponding fixtures location to /test subdirectories. 
* Changed minimum version for `composer-runtime-api` to `^2.1` in `composer.json` to align with `symfony/framework-bundle` and `symfony/twig-bundle` minimum requirements.
