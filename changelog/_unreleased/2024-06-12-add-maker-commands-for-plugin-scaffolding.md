---
title: Add maker commands for plugin scaffolding
issue: NEXT-36695
author: Raffaele Carelle
author_email: raffaele.carelle@gmail.com
author_github: raffaelecarelle
---
# Core
* Added generic `\Shopware\Core\Framework\Plugin\Command\MakerCommand` and `\Shopware\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass` to dynamically register CLI commands to generate specific parts of the plugin scaffold.
* Changed the phpstan type for `\Shopware\Core\Framework\Plugin\PluginEntity::$baseClass` from plain string to the more precise `class-string<Plugin>`
___
# Upgrade Information
## Separate plugin generation scaffolding commands

Instead of always generating a complete plugin scaffold with `bin/console plugin:create`, you can now generate specific parts of the plugin scaffold e.g. `bin/console make:plugin:config` to generate only the symfony config part of the plugin scaffold.
