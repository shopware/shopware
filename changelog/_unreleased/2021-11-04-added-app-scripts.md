---
title: Added app scripts
issue: NEXT-18248
---
# Core
* Added `Framework/Script` domain, to introduce scripting feature
* Added `\Shopware\Core\Migration\V6_4\Migration1635237551Script` to add new `script` table
* Added `\Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister` and `\Shopware\Core\Framework\App\Lifecycle\ScriptFileReader` to handle lifecycle of app scripts
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` and `\Shopware\Core\Framework\App\AppStateService` to manage lifecycle of scripts by apps
* Added support for `include` scripts, which can be reused in all other scripts which executed in a specific hook 
* Changed `\Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder` to support nested line items
* Added `\Shopware\Core\Checkout\Cart\Error\GenericCartError` as common base class for cart errors
* Added `Checkout/Cart/Facade` domain, as abstraction layer to manipulate the cart through app scripts
* Added `\Shopware\Core\Checkout\Cart\Hook\CartHook` as hook point for cart manipulations
* Added `\Shopware\Core\Checkout\Cart\Price\CurrencyPriceCalculator` and `\Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition` to support currency dependent price definitions
* Added `\Shopware\Core\Checkout\Cart\Processor\ContainerCartProcessor` to support calculation of nested line items
* Added `\Shopware\Core\Checkout\Cart\Processor\DiscountCartProcessor` to support calculation of discounts
* Changed `\Shopware\Core\Checkout\Cart\Processor` to execute scripts for the `cart` hook
* Changed `\Shopware\Core\Content\Product\Cart\ProductCartProcessor` to also process products in nested line items
* Added `\Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension` to support PHP style syntax inside twig templates
* Added `\Shopware\Core\Framework\Adapter\Twig\TwigEnvironment` to validate entity access from twig templates
* Added `Framework/DataAbstractionLayer/Facade` domain, as abstraction layer to use the DAL in app scripts
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer` to support `CurrencyPriceDefinitions`
* Added `\Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility` to define the visibility of internal fields in entities
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition` and `\Shopware\Core\Framework\DataAbstractionLayer\Entity` to support the `FieldVisibilities`
* Added `\Shopware\Core\Framework\Script\Service\ArrayFacade` to make array manipulations in twig scripts easier
* Added `\Shopware\Core\Framework\Script\Services` to allow autocompletion for services in twig scripts
* Added `System/SystemConfig/Facade` domain, as abstraction layer to access the system-config in app scripts
___
# Storefront
* Added HookClasses for every storefront page
* Changed storefront controller to execute scripts
* Added `script_traces.html.twig` template, to show debug output of scripts inside the symfony toolbar
* Changed the checkout templates to allow using storefront snippets as labels of line items inside the cart
___
# Upgrade Information
## AppScripts Feature
Apps can now include scripts to run synchronous business logic inside the shopware stack.
Visit the [official documentation](https://developer.shopware.com/docs/guides/plugins/apps) for more information on that feature.
___
# Upcoming Major Version Changes
## CustomerEntity changes

Following properties and methods on the `\Shopware\Core\Checkout\Customer\CustomerEntity` are marked as internal. 
* `$password`
* `$legacyEncoder`
* `$legacyPassword`
* `getPassword()`
* `setPassword()`
* `getLegacyEncoder()`
* `setLegacyEncoder()`
* `getLegacyPassword()`
* `setLegacyPassword()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## PaymentMethodEntity changes

Following properties and methods on the `\Shopware\Core\Checkout\Payment\PaymentMethodEntity` are marked as internal.
* `$handlerIdentifier`
* `getHandlerIdentifier()`
* `setHandlerIdentifier()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## FlowEntity changes

Following properties and methods on the `\Shopware\Core\Content\Flow\FlowEntity` are marked as internal.
* `$payload`
* `getPayload()`
* `setPayload()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## MediaFolderConfigurationEntity changes

Following properties and methods on the `\Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity` are marked as internal.
* `$mediaThumbnailSizesRo`
* `getMediaThumbnailSizesRo()`
* `setMediaThumbnailSizesRo()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## MediaEntity changes

Following properties and methods on the `\Shopware\Core\Content\Media\MediaEntity` are marked as internal.
* `$mediaTypeRaw`
* `$thumbnailsRo`
* `getMediaTypeRaw()`
* `setMediaTypeRaw()`
* `getThumbnailsRo()`
* `setThumbnailsRo()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## RuleEntity changes

Following properties and methods on the `\Shopware\Core\Content\Rule\RuleEntity` are marked as internal.
* `$payload`
* `getPayload()`
* `setPayload()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## AppEntity changes

Following properties and methods on the `\Shopware\Core\Content\Rule\RuleEntity` are marked as internal.
* `$payload`
* `getPayload()`
* `setPayload()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## DeadMessageEntity changes

Following properties and methods on the `\Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity` are marked as internal.
* `$serializedOriginalMessage`
* `getSerializedOriginalMessage()`
* `setSerializedOriginalMessage()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## PluginEntity changes

Following properties and methods on the `\Shopware\Core\Framework\Plugin\PluginEntity` are marked as internal.
* `$iconRaw`
* `getIconRaw()`
* `setIconRaw()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## WebhookEventLogEntity changes

Following properties and methods on the `\Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogEntity` are marked as internal.
* `$serializedWebhookMessage`
* `getSerializedWebhookMessage()`
* `setSerializedWebhookMessage()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## UserEntity changes

Following properties and methods on the `\Shopware\Core\System\User\UserEntity` are marked as internal.
* `$password`
* `$storeToken`
* `getPassword()`
* `setPassword()`
* `getStoreToken()`
* `setStoreToken()`

You should not use them in your plugins code anymore. Additionally, it is not possible anymore to access those properties within twig templates.

## Removal of storefront `ContactPage`

The `\Shopware\Storefront\Page\Contact\ContactPage` and the accompanying `\Shopware\Storefront\Page\Contact\ContactPageLoadedEvent` and `\Shopware\Storefront\Page\Contact\ContactPageLoader`
were removed, as they were not used anymore. Use the `\Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute` instead.
