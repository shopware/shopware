---
title: Add FallbackPluginLoader to build administration without database
issue: NEXT-15798
---
# Core
* Added new class `\Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader` to load plugins from composer without database instead
* Deprecated class `\Shopware\Core\Framework\Plugin\BundleConfigDumper`, use `\Shopware\Core\Framework\Plugin\BundleConfigGenerator` instead
___
# Upgrade Information

## Added support for building administration without database

In some setups it's common that the application is built with two steps in a `build` and `deploy` phase. The `build` process doesn't have any database connection.
Currently, Shopware needs to build the administration a database connection, to discover which plugins are active. To avoid that behaviour we have added a new `ComposerPluginLoader` which loads all information from the installed composer plugins.

To use the `ComposerPluginLoader` you have to create a file like `bin/ci` and setup the cli application with loader. There is an example:

```php
#!/usr/bin/env php
<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Production\HttpKernel;
use Shopware\Production\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;

set_time_limit(0);

$classLoader = require __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';

if (class_exists(Dotenv::class) && is_readable($envFile) && !is_dir($envFile)) {
    (new Dotenv())->usePutenv()->load($envFile);
}

if (!isset($_SERVER['PROJECT_ROOT'])) {
    $_SERVER['PROJECT_ROOT'] = dirname(__DIR__);
}

$input = new ArgvInput();
$env = $input->getParameterOption(['--env', '-e'], $_SERVER['APP_ENV'] ?? 'prod', true);
$debug = ($_SERVER['APP_DEBUG'] ?? ($env !== 'prod')) && !$input->hasParameterOption('--no-debug', true);

if ($debug) {
    umask(0000);

    if (class_exists(Debug::class)) {
        Debug::enable();
    }
}

$pluginLoader = new ComposerPluginLoader($classLoader, null);

$kernel = new HttpKernel($env, $debug, $classLoader);
$kernel->setPluginLoader($pluginLoader);

$application = new Application($kernel->getKernel());
$application->run($input);
```

With the new file we can now dump the plugins for the administration without database with the command `bin/ci bundle:dump`
