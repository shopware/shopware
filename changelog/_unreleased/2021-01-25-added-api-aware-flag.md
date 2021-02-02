---
title: Added api aware flag
issue: NEXT-13371
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware` flag, to define which fields are available over the APIs.
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected`, use `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware` instead
___
# Upgrade Information
## Api aware fields
So far, we have used a protection pattern on the entities, to define which fields are available through the APIs. This pattern has been used for the `/admin` API as well as for the `/sales-channel-api` and `/store-api`.
A field could previously be excluded from an API via the `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected`. This has now changed as follows:

* Every field is enabled for the `/admin` API by default. This happens via the base `\Shopware\Core\Framework\DataAbstractionLayer\Field\Field` class where we add the flag by default for the `/admin` API.
* To make a field available in the `/store-api` as well, the flag can be overwritten and the correct source can be specified in the new flag.
* By default, no information for an entity is available in the `/store-api`. Only by adding the flag the data becomes visible.
* If no source is passed to the flag, the flag will use all sources as default.
* If a field should not be available via any API, the flag can be removed via `->removeFlag(ApiAware::class)`.

* Example, make field available to all APIs (`/admin` and `/store-api`)
```php
(new TranslatedField('description'))->addFlags(new ApiAware())
```

* Example, make field available in `/store-api` only
```php
(new TranslatedField('description'))->addFlags(new ApiAware(SalesChannelApiSource::class))
```

* Example, remove field from all APIs:
```php
(new StringField('handler_identifier', 'handlerIdentifier'))->removeFlag(ApiAware::class)
```
