---
title: Support Resources/custom-fields.xml for plugins and apps
issue: #15114
---
# Core
* Added `\Shopware\Core\System\CustomField\CustomFieldSetPersister` to handle custom field set synchronization for both plugins and apps.
* Added `\Shopware\Core\System\CustomField\CustomFieldXmlLoader` to load and parse `Resources/custom-fields.xml` files.
* Added support for `Resources/custom-fields.xml` in plugins, automatically synced during install, update, and uninstall.
* Added support for `Resources/custom-fields.xml` in apps as an alternative to inline `<custom-fields>` in `manifest.xml`.
* Moved custom field XML parsing classes from `Shopware\Core\Framework\App\Manifest\Xml\CustomField` to `Shopware\Core\System\CustomField\Xml`.
* Changed `\Shopware\Core\System\CustomField\Xml\CustomFieldSet::toEntityArray` to accept nullable `$appId` parameter.
* Changed `\Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister` to delegate to `CustomFieldSetPersister` and prefer `Resources/custom-fields.xml` over inline manifest definition.
* Deprecated defining custom fields inline in `manifest.xml` via `<custom-fields>`, use `Resources/custom-fields.xml` instead.
