---
title: Parameter renaming in MigrationStep
issue: NEXT-33734
author: Michael Telgmann
author_email: m.telgmann@shopware.com
author_github: @mitelg
---
# Core
* Deprecated parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropColumnIfExists`. It will be changed to `columnName` with the next major version.
* Deprecated parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropForeignKeyIfExists`. It will be changed to `foreignKeyName` with the next major version.
* Deprecated parameter name `index` of `\Shopware\Core\Framework\Migration\MigrationStep::dropIndexIfExists`. It will be changed to `indexName` with the next major version.
___
# Upgrade Information
## Parameter names of some `\Shopware\Core\Framework\Migration\MigrationStep` methods will change
This will only have an effect if you are using the named parameter feature of PHP with those methods.
If you want to be forward compatible, call the methods without using named parameters.
* Parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropColumnIfExists` will change to `columnName`
* Parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropForeignKeyIfExists` will change to `foreignKeyName`
* Parameter name `index` of `\Shopware\Core\Framework\Migration\MigrationStep::dropIndexIfExists` will change to `indexName`
___
# Next Major Version Changes
## Parameter names of some `\Shopware\Core\Framework\Migration\MigrationStep` changed
* Parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropColumnIfExists` changed to `columnName` 
* Parameter name `column` of `\Shopware\Core\Framework\Migration\MigrationStep::dropForeignKeyIfExists` changed to `foreignKeyName` 
* Parameter name `index` of `\Shopware\Core\Framework\Migration\MigrationStep::dropIndexIfExists` changed to `indexName` 
