---
title: Public api rules
issue: NEXT-25424
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Changed class hierarchy of flow events, to not extend the basic `FlowEventAware`. You should implement the interface by yourself now. Following interfaces are affected and will no longer extend the `FlowEventAware` interface with v6.6:
  * `CustomerRecoveryAware`
  * `MessageAware`
  * `NewsletterRecipientAware`
  * `OrderTransactionAware`
  * `CustomerAware`
  * `CustomerGroupAware`
  * `MailAware`
  * `OrderAware`
  * `ProductAware`
  * `SalesChannelAware`
  * `UserAware`
  * `LogAware`
* Added new domain exception classes:
  * `CustomerException`
  * `PromotionException`
* Removed `EventSubscriberInterface` of `FlowIndexer` and introduced `FlowIndexerSubscriber` instead.
* Added `ResetInterface` to `ImportExport\AbstractEntitySerializer`
* Added `ImportExport\AbstractMediaSerializer` to define all public functions in an abstract class
* Removed `EventSubscriberInterface` from `ImportExport\MediaSerializer` and introduced `MediaSerializerSubscriber` instead.
* Deprecated `ImportExport\PriceFieldSerializer::isValidPrice`, function will be private in v6.6
* Deprecated `CsvReader::loadConfig`, function will be private in v6.6
* Deprecated `NewsletterSubscribeRoute.php`, function will be private in v6.6
* Added `AbstractProductStreamUpdater` to define all public functions in an abstract class
* Added `ResetInterface` to `AbstractProductPriceCalculator`
* Removed `EventSubscriberInterface` from `RuleIndexer` and introduced `RuleIndexerSubscriber` instead.
* Added missing public function of `Translator` class to `AbstractTranslator`
* Added `ResetInterface` to `AbstractTokenFilter`
* Deprecated `AbstractIncrementer::getDecorated`, increment are not designed for decoration pattern
* Deprecated `MySQLIncrementer`, implementation will be private, use abstract class for type hints
* Deprecated `RedisIncrementer`, implementation will be private, use abstract class for type hints
* Removed `getDecorated` from internal class `AbstractBaseContextFactory`
* Removed `getDecorated` from internal class `BaseContextFactory`
* Removed `getDecorated` from internal class `CachedBaseContextFactory`
* Removed `@internal` annotation from all elastic search admin indexers (`Elasticsearch\Admin\Indexer\*`)

___
# Upgrade Information
## Becomes internal or private
* Deprecated `AbstractIncrementer::getDecorated`, increment are not designed for decoration pattern
* Deprecated `MySQLIncrementer`, implementation will be private, use abstract class for type hints
* Deprecated `RedisIncrementer`, implementation will be private, use abstract class for type hints
* Deprecated `ImportExport\PriceFieldSerializer::isValidPrice`, function will be private in v6.6
* Deprecated `CsvReader::loadConfig`, function will be private in v6.6
* Deprecated `NewsletterSubscribeRoute.php`, function will be private in v6.6
___

# Next Major Version Changes
## FlowEventAware interface change 
With v6.6 we change the class hierarchy of the following flow event interfaces:
* `CustomerRecoveryAware`
* `MessageAware`
* `NewsletterRecipientAware`
* `OrderTransactionAware`
* `CustomerAware`
* `CustomerGroupAware`
* `MailAware`
* `OrderAware`
* `ProductAware`
* `SalesChannelAware`
* `UserAware`
* `LogAware`

When you have implemented one of these interfaces in one of your own event classes, you should now also implement the `FlowEventAware` interface by yourself.
This is necessary to ensure that your event class is compatible with the new flow event system.

**Before:**
```php
<?php declare(strict_types=1);

namespace App\Event;

use Shopware\Core\Framework\Log\LogAware;

class MyEvent implements LogAware
{
    // ...
}
```

**After:**

```php
<?php declare(strict_types=1);

namespace App\Event;

use Shopware\Core\Framework\Event\FlowEventAware;

class MyEvent implements FlowEventAware, LogAware
{
    // ...
}
```

