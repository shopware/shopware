# 6.7.5.0 (upcoming)
### More tech updates
* ProductStream IDs added to ElasticsearchProductDefinition

## Features

### Tax Calculation Logic

The tax-free detection logic if the cart changed to handle B2B and B2C customers separately.
Previously, enabling "Tax-free for B2C" in the country settings also affected B2B customers.
Now, tax rules are applied **correctly** based on the customer type.

### Robots.txt configuration
The rendering of the `robots.txt` file has been changed to support custom `User-agent` blocks and the full `robots.txt` standard.
For a detailed guide on how to use the new features and extend the functionality, please refer to our documentation guide [Extend robots.txt configuration](https://developer.shopware.com/docs/guides/plugins/plugins/content/seo/extend-robots-txt.html).

### Scheduled Task for cleaning up corrupted media entries
A new scheduled task `media.cleanup_corrupted_media` has been introduced.
It detects and removes corrupted media records, such as entries created by interrupted or failed file uploads that have no corresponding file on the filesystem.

## API

### Add the possibility to specify indexer in context

When you want to specify which indexer should run, you can add the `EntityIndexerRegistry::EXTENSION_INDEXER_ONLY` extension to the context as follows:

```php
$context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY,
    new ArrayEntity([
        ProductIndexer::STOCK_UPDATER // Only execute STOCK_UPDATER.
    ]),
);
```

When making a call to the Sync API, specify the required indexer in the header:

```bash
curl -X POST "http://localhost:8000/api/_action/sync" \
-H "indexing-only: product.stock" \
#...
```

## Core

### Robots.txt parsing
A new `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser` has been introduced to parse `robots.txt` files. This new service provides improved error tracking and adds new events for better extensibility.
As part of this change, the constructor for `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` is now deprecated for string parameters. You should use the new parser to create a `ParsedRobots` object to pass to the constructor instead.

### new JWT helper

Added new `Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator` and `Shopware\Core\Framework\JWT\Struct\JWTStruct` to build general structure for encoding and decoding JWT.

### Added PHP 8.5 polyfill

The new dependency `symfony/polyfill-php85` was added, to make it possible to already use PHP 8.5 features, like `array_first` and `array_last`

### Removal of old `changelog` handling

As we changed how we process and generate changelogs the "old" changelog files are no longer needed.
Therefore, we removed all the internal code used to generate and validate them.
The whole `Shopware\Core\Framework\Changelog` namespace was removed.
The code is not needed anymore, you should adjust the `RELEASE_INFO` and `UPGRADE` files manually instead.

### Deprecated the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

Refection has significantly improved in particular since PHP 8.1, therefore the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper` was deprecated and will be removed in the next major release.
See below for the explicit replacements:

```diff
- $property = ReflectionHelper->getProperty(MyClass::class, 'myProperty');
+ $property = \ReflectionProperty(MyClass::class, 'myProperty');
```

```diff
- $method = ReflectionHelper->getMethod(MyClass::class, 'myMethod');
+ $method = \ReflectionMethod(MyClass::class, 'myMethod');
```

```diff
- $propertyValue = ReflectionHelper->getPropertyValue($object, 'myProperty');
+ $propertyValue = \ReflectionProperty(MyClass::class, 'myProperty')->getValue($object);
```

```diff
- $fileName = ReflectionHelper->getFileName(MyClass::class);
+ $fileName = \ReflectionClass(MyClass::class)->getFileName();
```

### New constraint to check for existing routes

The new constraint `\Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked` checks if a route is available or already taken by another part of the application.

### Multiple payment finalize calls allowed

With the feature flag `REPEATED_PAYMENT_FINALIZE`, the `/payment-finalize` endpoint can now be called multiple times using the same payment token.
This behaviour will be the default in the next major release.
If the token has already been consumed, the user will be redirected directly to the finish page instead of triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Payment tokens are no longer deleted immediately after use. A new scheduled task automatically removes expired tokens to keep the `payment_token` table clean.

### Added sanitized HTML tag support for app snippets

Added sanitized HTML tag support for app snippets. App developers can now use HTML tags for better formatting within their snippets. The sanitizing uses the `basic` set of allowed HTML tags from the `html_sanitizer` config, ensuring that security-related tags such as `script` are automatically removed.

### App custom entity association handling

The behaviour creating associations with custom entities in apps changed.
Now an exception will be thrown if the referenced table does not exist, instead of creating a reference to the non-existing table.

To allow the schema updater to skip creating associations if the referenced table does not exist, improving flexibility and robustness during schema updates, a new optional attribute `ignore-missing-reference` was added to association types (`one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`).

Example usage:
```xml
<one-to-many name="custom_entity" reference="quote_comment" ignore-missing-reference="true" store-api-aware="false" on-delete="set-null" />
```

## Administration

### URL restrictions for product and category SEO URLs

When creating a SEO URL for a product or category, the URL is now checked for availability. Before it was possible to override existing URLs like `account` or `maintenance` with SEO URLs. Existing URLs are now blocked to be used as SEO URLs.

## Storefront

### Language selector twig blocks

New extensible Twig blocks `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` have been added to the language selector to allow custom flag implementations.

### Added specific `add-product-by-number` template
The `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig` are deprecated and a new template `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` was added.

Instead of overwriting any of the `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig`,
extend the new `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` file using the same blocks.

Change:
```
{% sw_extends '@Storefront/storefront/page/checkout/_page.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```
to:
```
{% sw_extends '@Storefront/storefront/component/checkout/add-product-by-number.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```

## App System

## Hosting & Configuration

### Sales Channel Replace URL Command

A new `sales-channel:replace:url` command was added to replace the url of a sales channel.
```bash
bin/console sales-channel:replace:url <previous_url> <new_url>
```

### Staging configuration

The disabled delivery check in `MailSender` now checks for the Staging Mode `core.staging`, the `shopware.staging.mailing.disable_delivery` configuration and the config setting `shopware.mailing.disable_delivery`.
Regardless of mode the config setting `shopware.mailing.disable_delivery` always allows disabling mail delivery.

## Critical fixes

### Product weight precision

The database column `product.weight` now uses `DECIMAL(15,6)` instead of `DECIMAL(10,3)` to keep gram-based measurements accurate when values are stored in kilograms.

# 6.7.4.0

### Plugin config default values

The default values for plugin config fields are now parsed according to the type of the field.
This means default values for `checkbox` and `bool` fields are parsed as boolean values, `int` fields are parsed as integer values, and `float` fields are parsed as float values.
Everything else is parsed as string values. With this the default values are now consistent based on the type of the field and the type does not depend on the actual value.
This makes it more consistent as otherwise the types could change when they are configured in the Administration.

### Deprecated SystemConfig exceptions

The exceptions

* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`

are now deprecated and will be removed in v6.8.0.0.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

### Deprecated SystemConfigService tracing methods

The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` are deprecated and will be removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.
For now the methods are still available, but they do nothing.

### Add the correct interface to filterable price definitions

If a price definition should be filterable, explicitly implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.

## Storefront

### Vimeo and YouTube Cookie Consent Separation

With this change, Vimeo and YouTube videos now use separate cookie consent entries and load immediately when cookies are accepted, improving user experience and GDPR compliance.

### Cookie offcanvas links in dynamically loaded content

Links to open the cookie offcanvas that are loaded dynamically (e.g., within the navigation offcanvas) now work correctly.
The `CookieConfiguration` plugin now uses event delegation instead of direct event listeners.

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your
implementation to use event delegation as well.

# 6.7.3.0

## Improvements

### Language handling

#### American English can be used in installer

American English can now be downloaded in the installer and can become the default shop language like any other language in Shopware.

#### Available languages can be managed from Shopware core

No plugin is needed anymore to install languages available from the Shopware translation platform.
The entire plugin has been built into the core.
Simply fetch and activate the language of your choice via the new bin/console commands.
Later, this feature will become available in the administration.

However, for any other language pack not available from the Shopware translation platform, you will still need a plugin.

You can fetch Shopware translations from the Shopware translation platform, which are stored on Github.
You can even help provide translations and use them in your shop a short time later!

Please note: As these are community-provided translations, we cannot guarantee that everything is translated 100% correctly.

Good news: The Language Pack plugin will continue to be maintained under our usual release policy.

Please see the [ADR](adr/2025-06-03-integrating-the-language-pack-into-platform.md) for more details.

#### Country-Agnostic Language Layer

Working with language codes in Shopware, such as en-GB (a combination of language and country), generally works well.
However, this approach can be quite maintenance-heavy: using multiple dialects, for example, British and American English, always leads to duplicated language snippets and can quickly become frustrating for translators.

To address this, we introduced an additional translation layer that reduces dialects to patch files, limiting duplication to only a small portion of the snippets.

Read the full story in this [ADR](adr/2025-09-01-adding-a-country-agnostic-language-layer.md). You can also find a detailed concept document for further reference.

### CMS / Shopping Experience

#### Block type labels

See the type of blocks directly when working with it as an editor.
This is especially useful if using third party plugins.
Thanks to @amenk!

https://github.com/shopware/shopware/pull/12334

#### 3D/canvas switching

Slider viewers are now rendered in respect to their visibility modus. This gives us a bit of more performance.
Thanks to @ffrank913 😉

https://github.com/shopware/shopware/pull/12642

#### Performance: Faster product category loading with a new index

Thanks to this pull request, queries on product.categories shall run ways faster than before: See https://github.com/shopware/shopware/pull/12657 by @vienthuong

#### Checkout & Promotions: More reliable shipping price matrix, credit notes, and promotion discount calculations

https://github.com/shopware/shopware/pull/12560 by @untilu29 actually fixes Shipping method cannot be applied to products below 1 EUR due to “Cart price from” default by @cramytech.

https://github.com/shopware/shopware/pull/12589 by @ennasus4sun fixes Credit notes are created cumulatively by @swagTKA.

https://github.com/shopware/shopware/pull/12603 by @socrec fixes Fixed Price delivery promotions cannot be excluded by janobi

#### 3D Viewer: Improved visuals with better camera distance and model placement

https://github.com/shopware/shopware/pull/12682 by ffrank913 fixes Incorrect model focus in SW6 standard CMS by himself

https://github.com/shopware/shopware/pull/12654 by ffrank913 fixes Incorrect frontend display of 3D glb files in SW6 standard CMS by MaximilianFo

### More tech updates

* Framework & API: Store-API cookie groups, new route exception handling, cleaner query parsing
* Platform ops / DX: Environment variable improvements, cache directory configurability, profiler disabled by default in production
* Build tooling: Admin build target updated to ES2023 (plugin authors should check compatibility)
* Deprecations / Breaking changes:
  * Removal of `controllerName` and `controllerAction` variables in templates
  * Deprecation of `Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher`
* Upgrade notes: DB migration for the new category index, admin build target upgrade, profiler defaults
