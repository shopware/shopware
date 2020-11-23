---
title: Fix uninstall of apps with custom field sets
issue: NEXT-11989
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister::updateCustomFields()` to use System scope, thus fixing the issue when trying to uninstall apps with custom field sets.
