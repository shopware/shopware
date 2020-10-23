[titleEn]: <>(Use composer packages within a plugin)
[metaDescriptionEn]: <>(This HowTo will show how you load composer packages for your plugin)
[hash]: <>(article:how_to_use_composer_packages_in_a_plugin)

## Overview

This guide will show you how to load custom composer packages. A common issue is to find the right entry point for loading additional composer dependencies. There are multiple pitfalls that you should avoid:
* You should not require the `autoload.php` in the top of your plugin file as this will load them before your plugin is activated and can break a whole system without your plugin being active.
* When you use classes from the external dependencies within your service definition you have to load them before the container is built.
* When you require an `autoload.php` the provided `ClassLoader` will prepend onto the class loader stack. This overrides class implementations with your shipped classes which might not match in the exact version and breaks the system.
* The `autoload.php` might exist in a ZIP file installation of your plugin but not when it is installed via composer in a composer project. So you have to detect different installation types.

## Load custom autoload file

Shopware is aware of this difficult task and solves it for you. You just have to implement the `loadAdditionalClassLoaders` method within your plugin. This will always be the right timing. Sometimes it is wanted to prepend the new `ClassLoader` onto the stack but it is more common task to append the `ClassLoader`. There is also a helping method for you to use that prevents every mentioned pitfall:

```php
<?php

namespace FooBar;

use Shopware\Core\Framework\Plugin;

class FooBar extends Plugin
{
    public function loadAdditionalClassLoaders(): void
    {
        parent::loadAdditionalClassLoaders();
        $this->appendClassLoaderFile(__DIR__ . '/vendor/autoload.php');
    }
}
```
