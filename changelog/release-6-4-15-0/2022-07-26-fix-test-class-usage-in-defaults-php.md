---
title: Fix test-class usage in Defaults.php
issue: NEXT-22584
---
# Core
* Changed `\Shopware\Core\Defaults` to not rely on `\Shopware\Core\Test\TestDefaults` anymore, thus fixing autoloading problems when the test namespaces are not autoloaded.
