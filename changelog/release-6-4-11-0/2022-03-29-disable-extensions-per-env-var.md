---
title: Disable extensions per env variable
issue: NEXT-20852
---
# Core 
* Changed entry files `index.php` and `shopware.php` to use `ComposerPluginLoader` if env variable `DISABLE_EXTENSIONS` is set to true.
* Added `\Shopware\Core\Framework\App\EmptyActiveAppsLoader`, that will be used if env variable `DISABLE_EXTENSIONS` is set to true.
* Changed `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader`, `\Shopware\Core\Framework\Script\Execution\ScriptExecutor` and `\Shopware\Core\Framework\Webhook\WebhookDispatcher` to do an early return if env variable `DISABLE_EXTENSIONS` is set to true.
___
# Upgrade information
## Disabling of custom extensions with .env variable

In cluster setups you can't dynamically install or update extensions, because those changes need to be done on every host server.
Therfore such operations should be performed during a deployment/rollout and not dynamically.

For this you now can set the variable `DISABLE_EXTENSIONS=1` in your `.env` file.
This will:
* Only load plugins that are installed over composer, all other plugins are ignored.
* Ignore all apps that may be installed.

Another advantage of that flag is that it reduces the amount of database queries shopware needs to perform on each request, and thus making shopware faster and reducing the load on the database. 
