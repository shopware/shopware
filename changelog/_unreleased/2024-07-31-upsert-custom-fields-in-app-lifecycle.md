---
title: Upsert custom fields in app lifecycle
issue: NEXT-37462
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister` to upsert existing custom fields from apps, instead of always creating new entities, thus solving issues that associations and search configs for the custom fields get lost on app updates.
