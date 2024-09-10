UPGRADE FROM 6.3.x.x to 6.4
=======================

# 6.4.18.1
## Twig filter whitelist for `map`, `filter`, `reduce` and `sort` 

The whitelist can be extended using a yaml configuration:

```yaml
shopware:
    twig:
        allowed_php_functions: [ "is_bool" ]
```

# 6.4.18.0
## Define country address formatting structure
From the next major v6.5.0.0, address of a country are no longer fixed, but you can modify it by drag-drop address elements in admin Settings > Countries > detail page > Address tab
The address elements are stored as a structured json in `country_translation.address_format`, the default structure can be found in `\Shopware\Core\System\Country\CountryDefinition::DEFAULT_ADDRESS_FORMAT`
## Extension can add custom element to use in address formatting structure
* Plugins can define their own custom snippets by placed twig files in `<pluginRoot>/src/Resources/views/snippets`, you can refer to the default Core address snippets in `src/Core/Framework/Resources/views/snippets/address`
* Use the respective mutations instead
## Deprecated manifest-1.0.xsd

With the upcoming major release we are going to release a new XML-schema for Shopware Apps. In the new schema we remove two deprecations from the existing schema.

1. attribute `parent` for element `module` will be required.

   Please make sure that every of your admin modules has this attribute set like described in [our documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-modules)
2. attribute `openNewTab` for element `action-button` will be removed.

    Make sure to remove the attribute `openNewTab` from your `action-button` elements in your `manifest.xml` and use ActionButtonResponses as described in our [documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-action-button) instead.
3. Deprecation of `manifest-1.0.xsd`

    Update the `xsi:noNamespaceSchemaLocation` attribute of your `manifest` root element. to `https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`
### MessageQueue Deprecations

For v6.5.0.0 we will remove our wrapper around the symfony messenger component and remove the enqueue integration as well. Therefore, we deprecated several classes for the retry and encryption handling, without replacement, as we  will use the symfony standards for that.

Additionally, we deprecated the `Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, you should directly implement the `\Symfony\Component\Messenger\Handler\MessageSubscriberInterface` instead.

Before:
```php
class MyMessageHandler extends AbstractMessageHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function handle(MyMessage $message): void
    {
        // do something
    }
}
```

After:
```php
class MyMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function __invoke(MyMessage $message): void
    {
        // do something
    }
}
```

# 6.4.17.0
* Themes' snippets are now only applied to Storefront sales channels when they or their child themes are assigned to that sales channel
## Disabling caching of store-api-routes
The Cache for Store-API-Routes can now be disabled by implementing the `Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent` and calling `disableCache()` method on the event.
## Limit remote URL file upload max file size
By default, there is no limit on how large a file is allowed to be when using the URL upload feature. The new parameter
`shopware.media.url_upload_max_size` can be used to limit the maximum file size. The values can be written in bytes or 
in a human-readable format like: 1mb, 512kb, 2gb. The default is 0 (unlimited).

# 6.4.16.0
## Added possibility to extend snippets in the Administration via App. 
* Snippets can be imported via AdminExtensionSDK
* Snippets will be validated to not override existing snippets
* Snippets will be sanitized to avoid script injection

# 6.4.15.2
## Changed icon.html.twig

We changed the base pathes to the icons in the template `Storefront/Resources/views/storefront/utilities/icon.html.twig`
If you have overwritten the block `utilities_icon` please change it as follows:

Before:
```twig
...
{% set icon =  source('@' ~ themeIconConfig[pack].namespace ~ '/../' ~ themeIconConfig[pack].path ~'/'~ name ~ '.svg', ignore_missing = true) %}
...
{% set icon = source('@' ~ namespace ~ '/../app/storefront/dist/assets/icon/'~ pack ~'/'~ name ~'.svg', ignore_missing = true) %}
...
```

After:
```twig
...
{% set icon =  source('@' ~ themeIconConfig[pack].namespace ~ '/' ~ themeIconConfig[pack].path ~'/'~ name ~ '.svg', ignore_missing = true) %}
...
{% set icon = source('@' ~ namespace ~ '/assets/icon/'~ pack ~'/'~ name ~'.svg', ignore_missing = true) %}
...
```

# 6.4.15.0
## Demodata generator registration in DI

Demodata generators now accepts the following new attributes:
* `option-name`: Option name for the command, optional if command has no option.
* `option-default`: Default value for the number of items to generate (Default: 0).
* `option-description`: Description for the command line option, not required.

```xml
<service id="Shopware\Core\Framework\Demodata\Generator\PropertyGroupGenerator">
    <argument type="service" id="property_group.repository" />
    
    <tag name="shopware.demodata_generator" option-name="properties" option-default="10" option-description="Property group count (option count rand(30-300))"/>
</service>
```
## Dump env vars
You can now dump the env vars to a optimized `env.local.php` file by running `bin/console system:setup --dump-env` or `bin/console dotenv:dump --env {APP_ENV}` command.
For more information on the `env.local.php` file, see the [symfony docs](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-production).

# 6.4.14.0
## Deprecate old document generation endpoint, introduce new bulk order's documents generator endpoint

* Endpoint and payload:
```
POST /api/_action/order/document/{documentType}/create
[
    {
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc98",
        "static": true,
        "config": {
            "documentComment": "Some comment",
            "documentNumber": "1002",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }, 
    {        
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc99",
        "static": true,
        "config": {
            "documentComment": "Another comment",
            "documentNumber": "1003",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }
]
```

## New bulk order's documents downloading endpoint

This endpoint is used for merging multiple documents at one pdf file and download the merged pdf file

* Endpoint and payload:
```
POST /api/_action/order/document/download
{
    "documentIds": [
        "012cd563cf8e4f0384eed93b5201cc98",
        "075fb241b769444bb72431f797fd5776",
    ],
}
```

## New Store-Api route to download document

* Use `/store-api/document/download/{documentId}/{deepLinkCode}` route to download generated document of the given id

## Deprecation of DocumentPageLoader

* The `\Shopware\Storefront\Page\Account\Document\DocumentPageLoader` and its page, page loaded event was deprecated and will be removed in v6.5.0.0 due to unused, please use the newly added `\Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute` instead to download generated document. 

## Deprecation of Document generators, introduce Document renderer services

* All the document generators in `Shopware\Core\Checkout\Document\DocumentGenerator` (tagged as `document.generator`) will be deprecated and will be removed in v6.5.0.0, please adjust your changes if you're touching these services, you might want to decorate `Shopware\Core\Checkout\Document\Renderer\*` (tagged as `document.renderer`) instead
* If you need to manipulate the fetched orders in renderer services, you can listen to according events which extends from `Shopware\Core\Checkout\Document\Event\DocumentOrderEvent`
## Replacing old icons
## Update `requestStateData` method in `form-country-state-select.plugin.js`
The method `requestStateData` will require the third parameter `stateRequired` to be set from the calling instance.
It will no longer be provided by the endpoint of `frontend.country.country-data`.
The value can be taken from the selected country option in `data-state-required`

# 6.4.13.0
## Added new plugin config field

Now you can declare a config field in your plugin `config.xml` to be available as scss variable.
The new tag is `<css>` and takes the name of the scss variable as its value.

```xml
<input-field>
    <name>myPluginBackgroundcolor</name>
    <label>Backgroundcolor</label>
    <label lang="de-DE">Hintergrundfarbe</label>
    <css>my-plugin-background-color</css>
    <defaultValue>#eee</defaultValue>
</input-field>

```
## Add support for Bootstrap v5 OffCanvas

Bootstrap has released a new OffCanvas component in version 5. To stick more towards the Bootstrap framework in the Storefront,
we have decided to migrate our custom OffCanvas solution to the Bootstrap v5 OffCanvas component.

Find out more about the Bootstrap OffCanvas here: https://getbootstrap.com/docs/5.1/components/offcanvas/

In general, the changes are mostly done internally, so that interacting with the OffCanvas via JavaScript can remain the same.
However, when the major flag `V6_5_0_0` is activated, the OffCanvas module will open a Bootstrap OffCanvas with slightly different elements/classes.

Let's take a look at an example, which opens an OffCanvas using our OffCanvas module `src/plugin/offcanvas/offcanvas.plugin`:
```js
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

// No need for changes in general usage!
OffCanvas.open(
    'My content', // Content to render inside the OffCanvas
    () => {},     // Callback function to run after opening the OffCanvas
    'right',      // Position
    true,         // Can be closed via the backdrop
    100,          // Delay
    true,         // Full-width OffCanvas
    'my-class'    // Additional CSS classes for the OffCanvas element
);
```
The above example, will work as expected, but it will yield different HTML in the DOM:

**Opened OffCanvas with current implementation**
```html
<div class="offcanvas is-right is-open">
    My content
</div>
<div class="modal-backdrop modal-backdrop-open"></div>
```

**Opened OffCanvas with Bootstrap v5 (V6_5_0_0=true)**
```html
<!-- `right` is now called `end` in Bootstrap v5. This will be converted automatically. -->
<!-- `show` is now used instead of `is-open` to indicate the active state. -->
<div class="offcanvas offcanvas-end show" style="visibility: visible;" aria-modal="true" role="dialog">
    My content
</div>

<!-- Bootstrap v5 uses a dedicated backdrop for the OffCanvas. -->
<div class="offcanvas-backdrop fade show"></div>
```

Furthermore, Bootstrap v5 needs slightly different HTML inside the OffCanvas itself. This needs to be considered,
if you inject your HTML manually via JavaScript:

```js
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import Feature from 'src/helper/feature.helper';

let offCanvasContent;

// OffCanvas now needs additional `offcanvas-header`
// Content class `offcanvas-content-container` is now `offcanvas-body`
if (Feature.isActive('v6.5.0.0')) {
    offCanvasContent = `
    <div class="offcanvas-header p-0">
        <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
            Close
        </button>
    </div>
    <div class="offcanvas-body">
        Content
    </div>
    `;
} else {
    offCanvasContent = `
    <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
        Close
    </button>
    <div class="offcanvas-content-container">
        Content
    </div>
    `;
}

// No need for changes in general usage!
OffCanvas.open(
    offCanvasContent // Use altered HTML, if Bootstrap v5 is used
);
```

If you use `src/plugin/offcanvas/ajax-offcanvas.plugin` with a response which is based on `Resources/views/storefront/utilities/offcanvas.html.twig`, 
you don't need to change anything. The markup inside the OffCanvas twig file is adjusted automatically to Bootstrap v5 markup.
## Removed repository decorators
The following repository decorator classes will be removed with the next major:
* `MediaRepositoryDecorator`
* `MediaThumbnailRepositoryDecorator`
* `MediaFolderRepositoryDecorator`
* `PaymentMethodRepositoryDecorator`

If you use one of the repository and type hint against this specific classes, you have to change you type hints to `EntityRepository`:

### Before
```php
private MediaRepositoryDecorator $mediaRepository;
```

### After
```php
private EntityRepositoryInterface $mediaRepository;
```

## Thumbnail repository flat ids delete
The `media_thumbnail.repository` had an own implementation of the `EntityRepository`(`MediaThumbnailRepositoryDecorator`) which breaks the nested primary key pattern for the `delete` call and allows providing flat id arrays. If you use the repository in this way, you have to change the usage as follow:

### Before
```php
$repository->delete([$id1, $id2], $context);
```

### After
```php
$repository->delete([
    ['id' => $id1], 
    ['id' => $id2]
], $context);
```

## `@internal` entity repositories
We marked the `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` classes as `@deprecated` and will be removed and the `EntityRepository` & `SalesChannelRepository` as final, to be able to release future optimizations more easily. Therefor if you implement an own repository class for your entities, you have to remove this. To modify the repository calls you can use one of the following events:
* `BeforeDeleteEvent`: Allows an access point for before and after deleting the entity
* `EntitySearchedEvent`: Allows access points to the criteria for search and search-ids
* `PreWriteValidationEvent`/`PostWriteValidationEvent`: Allows access points before and after the entity written
* `SalesChannelProcessCriteriaEvent`: Allows access to the criteria before the entity is search within a sales channel scope

Additionally, you have to change your type hints from `EntityRepositoryInterface` to `EntityRepository` or `SalesChannelRepository`:

# 6.4.12.0
## Refactoring of storefront line item twig templates

With the next major release we want to unify the twig templates, which are used to display line items in the storefront.
Right now, there are multiple different templates for different areas in which line items are displayed:
* Cart, confirm and finish page
* OffCanvas Cart
* Account order details

Those different templates will be removed in favor of a new line item base template, which can be adjusted via configuration variables.
Furthermore, each known line item type will have its own sub-template to avoid too many if/else conditions within the line item base template.
This will also future-proof the line item base template for possible new line item types. 
There will be no more separate `-children` templates for nested line items. Nested line items will also be covered by the new base template.

* New line item template: `Resources/views/storefront/component/line-item/line-item.html.twig`
    * Config variables:
        * `displayMode` (string) - Toggle the appearance of the line item
            * `default` - Full line item appearance including mobile and desktop styling
            * `offcanvas` - Appearance will always stay mobile, regardless of the viewport size. Provides additional classes for OffCanvas JS-plugins
            * `order` - Appearance for display inside the account order list
        * `showTaxPrice` (boolean) - Show the tax price instead of the unit price of the line item.
        * `showQuantitySelect` (boolean) - Show a select dropdown to change the quantity. When false it only displays the current quantity as text.
        * `redirectTo` (string) - The redirect route, which should be used after performing actions like "remove" or "change quantity".
    * types:
        * `product` - Display a product line item including preview image, additional information and link to product.
        * `discount` - Display a discount line item and skip all unneeded information like "variants".
        * `container` - Display a container line item, which can include nested line items.
        * `generic` - Display a line item with an unknown type, try to render as much information as possible.
## Apps can now require additional ACL privileges

In addition to requiring CRUD-permission on entity basis, apps can now also require additional ACL privileges.
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
    ...
    </meta>
    <permissions>
        <create>product</create>
        <update>product</update>
        <permission>user_change_me</permission>
    </permissions>
</manifest>
```
## Apps can now associate custom field sets to more entities

Apps can now add custom field sets to the following additional entities:
* landing_page
* promotion
* product_stream
* property_group
* product_review
* event_action
* country
* currency
* customer_group
* delivery_time
* document_base_config
* language
* number_range
* payment_method
* rule
* salutation
* shipping_method
* tax
## Only configured custom fields will be indexed in Elasticsearch

With Shopware 6.5 only configured customFields in the YAML file will be indexed, to reduce issues with type errors.
The config can be created in the `config/packages/elasticsearch.yml` with the following config

```yaml
elasticsearch:
  product:
    custom_fields_mapping:
      some_date_field: datetime
```

See [\Shopware\Core\System\CustomField\CustomFieldTypes](https://github.com/shopware/platform/blob/0ca57ddee85e9ab00d1a15a44ddc8ff16c3bc37b/src/Core/System/CustomField/CustomFieldTypes.php#L7-L19) for the complete list of possible options

# 6.4.11.0
## Introduce BeforeDeleteEvent
The event is dispatched before delete commands are executed, so you can add success callbacks into the event when the delete command is successfully executed. Or you add error callbacks to the event when the execution meets some errors.

**Reference: Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent**

**Examples:**

```php
class YourBeforeDeleteEvent implements EventSubscriberInterface
    public static function getSubscribedEvents()
    {
        return [
            BeforeDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $context = $event->getContext();
        
        // Delete ids of the given entity
        // At the given point, the ids are not deleted yet
        $ids = $event->getIds(CustomerDefinition::ENTITY_NAME);

        $event->addSuccess(function (): void {
            // Implement the hook when the entities got deleted successfully
            // At the given point, the $ids are deleted
        });

        $event->addError(function (): void {
            // At the given point, the $ids are not deleted due to failure
            // Implement the hook when the entities got deleted unsuccessfully
        });
    }
}
```
## Modal Refactoring

Previously you had to use the following snippet:
```js
import AjaxModalExtension from 'src/utility/modal-extension/ajax-modal-extension.util';
new AjaxModalExtension(false);
```
to activate modals on elements that match the selector `[data-toggle="modal"][data-url]`.
This is error-prone when used multiple times throughout a single page lifetime as it will open up modals for every execution of this rigid helper.
In the future you can use the new storefront plugin `AjaxModalPlugin` which has more configuration and entrypoints for developers to react to or adjust behaviour.
The plugin is registered to the same selector to ensure non-breaking upgrading by default.
## Removal of deprecated route specific annotations

The following annotations has been removed `@Captcha`, `@LoginRequired`, `@Acl`, `@ContextTokenRequired` and `@RouteScope` and replaced with Route defaults. See below examples of the migration

### @Captcha

```php
/**
 * @Captcha
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_captcha"=true})
 */
```

### @LoginRequired

```php
/**
 * @LoginRequired
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_loginRequired"=true})
 */
```

### @Acl

```php
/**
 * @Acl({"my_plugin_do_something"})
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_acl"={"my_plugin_do_something"}})
 */
```


### @ContextTokenRequired

```php
/**
 * @ContextTokenRequired
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_contextTokenRequired"=true})
 */
```

### @RouteScope

```php
/**
 * @RouteScope(scopes={"api"})
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_routeScope"={"api"}})
 */
```
## New Twig filter sw_icon_cache
From now on, all icons implemented via `sw_icon` is wrapped with `sw_icon_cache`. 
This causes all icons only be defined once per html page and multiple occurences be referenced by id.
### Example
First implementation of the `star` icon:
```html
<svg xmlns="http://www.w3.org/2000/svg" 
     xmlns:xlink="http://www.w3.org/1999/xlink" 
     width="24" height="24" viewBox="0 0 24 24">
    <defs>
        <path id="icons-solid-star" 
              d="M6.7998 23.3169c-1.0108.4454-2.1912-.0129-2.6367-1.0237a2 2 0 0 1-.1596-1.008l.5724-5.6537L.7896 11.394c-.736-.8237-.6648-2.088.1588-2.824a2 2 0 0 1 .9093-.4633l5.554-1.2027 2.86-4.9104c.556-.9545 1.7804-1.2776 2.7349-.7217a2 2 0 0 1 .7216.7217l2.86 4.9104 5.554 1.2027c1.0796.2338 1.7652 1.2984 1.5314 2.378a2 2 0 0 1-.4633.9093l-3.7863 4.2375.5724 5.6538c.1113 1.0989-.6894 2.08-1.7883 2.1912a2 2 0 0 1-1.008-.1596L12 21.0254l-5.2002 2.2915z">
        </path>
    </defs>
    <use xlink:href="#icons-solid-star"></use>
</svg>
```
Following implementations of the `star` icon:
```html
<svg xmlns="http://www.w3.org/2000/svg" 
     xmlns:xlink="http://www.w3.org/1999/xlink" 
     width="24" height="24" viewBox="0 0 24 24">
    <use xlink:href="#icons-solid-star"></use>
</svg>
```
This behaviour can be disabled by setting the system config `core.storefrontSettings.iconCache` to `false`.
The Setting can be found in the administration under `Settings`-> `System`->`Storefront`->`Activate icon cache`
From 6.5.0.0 on this will be enabled by default.

You can enable and disable this behaviour on a template basis by calling the new twig function `sw_icon_cache_enable`
and `sw_icon_cache_disable`.

## New Command theme:prepare-icons
The new command `theme:prepare-icons` prepares svg icons for usage in the storefront with compatibility with the icon cache.
The command requires a path for the icons to prepare and a package name for the icons and will save all updated icons to a subdirectory `prepared`.
Optional you can also set the following options:
* --cleanup (true|false) - This will remove all unnecessary attributes from the icons.
* --fillcolor (color) - This will add this colorcode to the `fill` attribute.
* --fillrule (svg fill rule) - This will add the fill rule to the `fill-rule` attribute
```
/bin/console theme:prepare-icons /app/platform/src/Storefront/Resources/app/storefront/dist/assets/icon/default/ default -c true -r evenodd -f #12ef21
```
## Better profiling integration
Shopware now supports better profiling for multiple integrations.
To activate profiling and a specific integration, add the corresponding integration name to the `shopware.profiler.integrations` parameter in your shopware.yaml file.
## Translation overwrite priority specified for write payloads

We specified the following rules for overwrites of translation values in write-payloads inside the DAL.
1. Translations indexed by `iso-code` take precedence over values indexed by `language-id`
2. Translations specified on the `translations`-association take precedence over values specified directly on the translated field.

For a more information on those rules refer to the [according ADR](/adr/2022-03-29-specify-priority-of-translations-in-dal-write-payloads.md).

Let's take a look on some example payloads, to see what those rules mean.
**Note:** For all examples we assume that `en-GB` is the system language.

### Example 1
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            'en-GB' => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because values in `translations` take precedence over those directly on the translated fields.
### Example 2
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because of the same reasons as above.
### Example 3
Payload:
```php
[
    'name' => [
        Defaults::SYSTEM_LANGUAGE => 'id translation',
        'en-GB' => 'iso-code translation',
    ],
]
```
Result: `iso-code translation`, because `iso-code` take precedence over `language-id`.
### Example 4
Payload:
```php
[
    'name' => 'default',
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'id translation',
            'en-GB' => 'iso-code translation',
         ],
    ],
]
```
Result: `iso-code translation`, because `iso-code` take precedence over `language-id`.
### Example 5
Payload:
```php
[
    'name' => [
       Defaults::SYSTEM_LANGUAGE => 'default', 
    ],
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `en translation`, because values in `translations` take precedence over those directly on the translated fields.
### Example 6
Payload:
```php
[
    'name' => [
       'en-GB' => 'default', 
    ],
    'translations' => [
        'name' => [
            Defaults::SYSTEM_LANGUAGE => 'en translation',
         ],
    ],
]
```
Result: `default`, because `iso-code` take precedence over `language-id`, and that rule has a higher priority then the second "association rule".
## Webhooks contain unique event identifier
All webhooks now contain a unique identifier that allows your app to identify the event.
The identifier can be found in the JSON-payload under the `source.eventId` key.

```json
{
    "source": {
        "url": "http:\/\/localhost:8000",
        "appVersion": "0.0.1",
        "shopId": "dgrH7nLU6tlE",
        "eventId": "7b04ebe416db4ebc93de4d791325e1d9"
    }
}

```
This identifier is unique for each original event, it will not change if the same request is sent multiple times due to retries, 
because your app maybe did not return a successful HTTP-status on the first try.
## Redis store for number range increments
You can now generate the number range increments using redis instead of the Database.
In your `shopware.yaml` specify that you want to use the redis storage and the url that should be used to connect to the redis server to activate this feature:
```yaml
shopware:
  number_range:
    increment_storage: "Redis"
    redis_url: "redis://redis-host:port/dbIndex"
```

To migrate the increment data that is currently stored in the Database you can run the following CLI-command:
```shell
bin/console number-range:migrate SQL Redis
```
This command will migrate the current state in the `SQL` storage to the `Redis` storage.
**Note:** When running this command under load it may lead to the same number range increment being generated twice.
## Apps can now require additional ACL privileges

In addition to requiring CRUD-permission on entity basis, apps can now also require additional ACL privileges.
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
    ...
    </meta>
    <permissions>
        <create>product</create>
        <update>product</update>
        <permission>user_change_me</permission>
    </permissions>
</manifest>
```

# 6.4.9.0
## Bootstrap v5 preview

We want to update the Storefront to Bootstrap v5 in the next major release of Shopware.
Because Bootstrap v5 introduces breaking changes when updating from Bootstrap v4, we have implemented the update behind a feature flag.
This gives you the possibility to test Bootstrap v5 with your apps or themes before the next major release. The current Bootstrap v4 implementation is still the default.
With the next major release Bootstrap v5 will be the default.

**The Bootstrap v5 preview should not be used in production environments because it is still under development!**

## What happens when updating to Bootstrap v5?

* Dropped jQuery dependency (It can be added manually if needed, see "Still need jQuery?")
* Dropped Internet Explorer 10 and 11
* Dropped Microsoft Edge < 16 (Legacy Edge)
* Dropped Firefox < 60
* Dropped Safari < 12
* Dropped iOS Safari < 12
* Dropped Chrome < 60

You can find a full migration guide on the official Bootstrap website: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)

## Activate Bootstrap v5

* Activate the next major feature flag `V6_5_0_0` in your .env or .psh.override.yaml
* Re-build the storefront using `psh.phar storefront:build`
* During the build process webpack will show a warning that Bootstrap v5 is being used
* If the Bootstrap v5 resources are not build, please try running `bin/console feature:dump` and try again

## How to consider Bootstrap v5

Because of the breaking changes inside Bootstrap v5 you will find several places with backward-compatibility code in the Shopware platform.
This code is being used to already provide the Bootstrap v5 implementation while keeping the Bootstrap v4 implementation for backward-compatibility.
Depending, if you are an app/theme developer or a platform contributor you need to adapt the backward-compatibility for your use case.

* **For platform contributors**: Use feature flag conditions.<br>
  Please use feature flag conditions with flag `V6_5_0_0` to migrate to Bootstrap v5 functionality while keeping the Bootstrap v4 implementations for backward-compatibility.
* **For app/plugin/theme developers**: Migrate your code directly to Bootstrap v5.<br>
  Please migrate your code directly to Bootstrap v5 e.g. by preparing a separate git branch. The feature flag `V6_5_0_0` should only be used to activate Bootstrap v5 during development.
  Please do not use the feature flag conditions in your app/plugin or theme.

You can find some code examples below which will illustrate this. There are always three examples for the same code snippet:

1. Bootstrap v4 (Current implementation) - How it looks right now
2. Bootstrap v5 with backward-compatibility (for platform contributors)
3. Bootstrap v5 next major - How it will look after the release of v6.5.0.0 (for app/plugin/theme developers)

**Please beware that this is only needed for areas which are effected by braking changes from Bootstrap v5. See: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)**

### HTML/Twig

#### 1. Bootstrap v4 (Current implementation):
```html
<button class="collapsed btn"
        data-toggle="collapse"
        data-target="#target-selector">
    Collapse button
</button>

<a href="#" class="btn btn-block">Default button</a>
```
#### 2. Bootstrap v5 with backward-compatibility (for platform contributors):

**Attention:** There are a good amount of attributes and classes which have been renamed inside Bootstrap v5.
To avoid having too many `{% if %}` conditions in the template we have created global twig variables for attribute renaming.

```html
{# Use global twig variable `dataBsToggleAttr` to toggle between `data-toggle` and `data-bs-toggle`: #}
<button class="collapsed btn"
        {{ dataBsToggleAttr }}="collapse"
        {{ dataBsTargetAttr }}="#target-selector">
    Collapse button
</button>

{# For larger markup changes use regular feature conditions: #}

{# @deprecated tag:v6.5.0 - Bootstrap v5 removes `btn-block` class, use `d-grid` wrapper instead #}
{% if feature('v6.5.0.0') %}
    <div class="d-grid">
        <a href="#" class="btn">Default button</a>
    </div>
{% else %}
    <a href="#" class="btn btn-block">Default button</a>
{% endif %}
```
#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```html
<button class="collapsed btn"
        data-bs-toggle="collapse"
        data-bs-target="#target-selector">
    Collapse button
</button>

<div class="d-grid">
    <a href="#" class="btn">Default button</a>
</div>
```

### SCSS

#### 1. Bootstrap v4 (Current implementation):
```scss
.page-link {
    line-height: $custom-select-line-height;
}
```
#### 2. Bootstrap v5 with backward-compatibility (for platform contributors):

Attention:
```scss
.page-link {
    // @deprecated tag:v6.5.0 - Bootstrap v5 renames variable $custom-select-line-height to $form-select-line-height
    @if feature('V6_5_0_0') {
        line-height: $form-select-line-height;
    } @else {
        line-height: $custom-select-line-height;
    }
}
```
#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```scss
.page-link {
    line-height: $form-select-line-height;
}
```

### JavaScript

#### 1. Bootstrap v4 (Current implementation):
```js
$(collapse).collapse('toggle');
```
#### 2. Bootstrap v5 with backward-compatibility (for platform contributors):
```js
// Use feature.helper to check for feature flags.
import Feature from 'src/helper/feature.helper';

/** @deprecated tag:v6.5.0 - Bootstrap v5 uses native HTML elements to init Collapse plugin */
if (Feature.isActive('V6_5_0_0')) {
    new bootstrap.Collapse(collapse, {
        toggle: true,
    });
} else {
    $(collapse).collapse('toggle');
}
```
#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```js
new bootstrap.Collapse(collapse, {
    toggle: true,
});
```

## Known issues

Since Bootstrap v5 is still behind the next major feature flag `V6_5_0_0` it is possible that issues occur.
The following list contains issues that we are aware of. We want to address this issues before the next major version.

* **Styling**<br>
  There might be smaller styling issues here and there. Mostly spacing or slightly wrong colors.
* **Bootstrap v5 OffCanvas**<br>
  Bootstrap v5 ships its own OffCanvas component. Shopware is still using its custom OffCanvas at the moment.
  It is planned to migrate the Shopware OffCanvas to the Bootstrap OffCanvas.
* **Modifying SCSS $theme-colors**<br>
  Currently it is not possible to add or remove custom colors to $theme-colors like it is described in the [Bootstrap documentation](https://getbootstrap.com/docs/5.1/customize/sass/#add-to-map).
## Allow generating multiple document types at backend
* Changed `Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction` to be able to create single document and multiple documents

## Allow selecting multiple document types at generating document action in the flow builder.
* We are able to select multiple document types in a generated document action in the flow builder.
* The flow builder is to be able to show the action with the configuration data as a single document or multiple documents.
* the configuration schema payload in the flow builder for this action will change:

Before:
```json
"config": {
  "documentType": "credit_note",
  "documentRangerType": "document_credit_note"
},
```

After:
```json
"config": {
  "documentTypes": [
    {
      "documentType": "credit_note",
      "documentRangerType": "document_credit_note"
    },
    {
      "documentType": "delivery_note",
      "documentRangerType": "document_delivery_note"
    }
  ]
},
```

# 6.4.8.2
## Proxy route to switch customer requires ACL privilege
If you want to use the route `api.proxy.switch-customer` you **MUST** have the privilege `api_proxy_switch-customer`.


# 6.4.8.0
## Adding search matcher configuration
When you want to your module appear on the search bar, you can define the  `searchMatcher` in the module’s metadata, otherwise, a default `searchMatcher `will be used as it will check your module’s metadata label if it’s matched with the search term, The search function should return an array of results that will appear on the search bar.

Example usage:

```
Module.register('sw-module-name', {
  ...
  
  searchMatcher: (regex, labelType, manifest) => {
    const match = labelType.toLowerCase().match(regex);

    if (!match) {
      return false;
    }

    return [
      {
        icon: manifest.icon,
        color: manifest.color,
        label: labelType,
        entity: '...',
        route: '...',
        privilege: '...',
      },
    ];
  }
})
```
## Adding default search configuration
* Adding a new js file (**`default-search-configuration.js`**) with the same folder level of the index.js (which is located at `src/Administration/Resources/app/administration/src/module/sw-module-name/index.js`)
```
src
│
└───sw-module-name
│   │
│   └───component
│   │
│   └───page
│   │
│   └───service
│   │
│   └───....
│   │ 
│   │   default-search-configuration.js
│   │   index.js

```
to determine the list of the ranking fields of the entity with default configuration (score and searchable) and adding two new properties in each module definition (`index.js`),
- `defaultSearchConfigurations` (mandatory): import from `./default-search-configuration.js`
- `entityDisplayProperty` (optional, default is `name`): determine the property of the module's entity to show on the `sw-search-bar`
## Injecting service for search ranking purpose
* Added `searchRankingService` into `inject` in whichever component you want to implement the search ranking.
* After that, you need to update your criteria by adding the search query score.
* We provide 4 open api from `searchRankingService` to handle these functions below:
    * Using `getSearchFieldsByEntity` to get all search ranking fields of the specific entity
        ```javascript
            const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
        ```
    * Using `buildSearchQueriesForEntity` to build a new criteria with the query score based on search ranking fields
        ```javascript
            const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
            let criteria = this.searchRankingService.buildSearchQueriesForEntity(searchFields, searchTerm, criteria);
        ```
    * Using `getUserSearchPreference` to get all search ranking fields from all module (the module has already defined `defaultSearchConfigurations`)
        ```javascript
            const userSearchPreference = this.searchRankingService.getUserSearchPreference();
        ```
    * Using `buildGlobalSearchQueries` to build a new criteria with the query score based on search ranking fields for composite search purpose
        ```javascript
          const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
        ```
## How to use search ranking service for component which already injected mixin `listing.mixin.js`
* Mixin `listing.mixin.js` already injected `searchRankingService` by default
* Just need to overwrite the data `searchConfigEntity` in `/src/app/mixin/listing.mixin.js` by assigned the module's entity name
    * Example, component `/src/module/sw-customer/page/sw-customer-list/index.js` want to implement search ranking service for listing:
        1. Injected `listing.mixin.js` into mixin
        2. Added data `searchRankingService: 'customer'` into the component
## Updated the way to search ranking fields from service `/src/app/service/search-ranking.service.js`
* Changed method `getSearchFieldsByEntity` and `buildSearchQueriesForEntity` to async function because we will need to fetch the user's search preferences from the server:
    * Using `getSearchFieldsByEntity` to get all search ranking fields of the specific entity
        ```javascript
            const searchFields = await this.searchRankingService.getSearchFieldsByEntity('product');
        ```
    * Using `getUserSearchPreference` to get all search ranking fields from all module (the module has already defined `defaultSearchConfigurations`)
        ```javascript
            const userSearchPreference = await this.searchRankingService.getUserSearchPreference();
        ```
## Added new way to search and upsert configuration just only for current logged-in user
* Using new service `userConfigService` from `/src/core/service/api/user-config.api.service.js`
    * Using `search` to get the configurations from list provided keys
        ```javascript
            // For specific key
            const config = await this.userConfigService.search(['key1', 'key2']);
      
            // For getting all configurations
            const config = await this.userConfigService.search();
        ```
  * Using `upsert` to update or insert the configurations
      ```javascript
          const config = await this.userConfigService.upsert({
              key1: [value1],
              key2: [value2]
          });
      ```
## Deprecated composite search api

The composite search api endpoint `api.composite.search` will be deprecated in the next major version. For the replacement we introduce a new endpoint in `Administration`: POST `api/_admin/search`

With this new endpoint you need to define which entities you want to search and its own criteria in the request body

### Before

Request:
```
[GET|POST] /api/_search?term=test&limit=25
```

Response
```json
{
    "data":[
        {"entity":"landing_page","total":0,"entities":[...]},
        {"entity":"order","total":0,"entities":[...]},
        {"entity":"customer","total":0,"entities":[...},
        {"entity":"product","total":0,"entities":[...]},
        {"entity":"category","total":0,"entities":[...]},
        {"entity":"media","total":0,"entities":...]},
        {"entity":"product_manufacturer","total":0,"entities":[...]},
        {"entity":"tag","total":0,"entities":[...]},
        {"entity":"cms_page","total":0,"entities":[...]}
    ]
}
```

### After

Request
```
[POST] /api/_admin/search

Body

{
    "product": {
        "page": 1,
        "limit": 25,
        "term": "test",
    },
    "category": {
        "page": 1,
        "limit": 25,
        "query": {...},
    },
    "custom_entity": {
        "page": 1,
        "limit": 25,
        "query": {...},
    },
}
```

Response
```json
{
    "data":{
        "product": {"total":0,"data":[...]},
        "category": {"total":0,"data":[...]},
        "custom_entity": {"total":0,"data":...]},
        // Or if the user do not have the read privileges within their request filter/query/associations...
        "customer": {
            "status": "403",
            "code": "FRAMEWORK__MISSING_PRIVILEGE_ERROR",
            "title": "Forbidden",
            "detail": "{'message':'Missing privilege','missingPrivileges':['customer:read']}",
            "meta": {
                 "parameters": []
            }
        }
    }
}
```

## Deprecated SearchApiService::search in administration

With the same reason, the `SearchApiService::search` in `src/core/service/api/search.api.service.js` will be replaced with `SearchApiService::searchByQuery`

### Usage

```js
const productCriteria = new Criteria();
const manufacturerCriteria = new Criteria();
productCriteria.addQuery(Criteria.contains('name', searchTerm), 5000);
manufacturerCriteria.addQuery(Criteria.contains('name', searchTerm), 5000);

const queries = { product: productCriteria, product_manufacturer: manufacturerCriteria };

this.searchService.searchQuery(queries).then((response) => {
    ...
});
```
## Get a list of user configuration from current logged-in user
### Before
Request:
```
[POST] /api/search/user-config

Body
{
   "page":1,
   "limit":25,
   "filter":[
      {
         "type":"equals",
         "field":"key",
         "value":"search.preferences"
      },
      {
         "type":"equals",
         "field":"userId",
         "value":"71d4deac6d7b410c982d1f1883960e25"
      }
   ],
   "total-count-mode":1
}
```
Response
```json
{
    "data": [
        {
            "id": "c5bf30ddca1449a480a161b1130cf640",
            "type": "user_config",
            "attributes": {
                "userId": "71d4deac6d7b410c982d1f1883960e25",
                "key": "search.preferences",
                "value": [
                    {
                        "order": {
                            "tags": {
                                "name": {
                                    "_score": 500,
                                    "_searchable": false
                                }
                            }
                        }
                    }
                ]
            }
        }
    ]
}
```
### After
Request:
```
[GET] /api/_info/config-me?keys[]=key1&keys[]=keys2
```
Response

Case 1: The key exists

Status code: 200
```json
{
    "data": {
        "key1" : {
            "order": {
                "tags": {
                    "name": {
                        "_score": 500,
                        "_searchable": false
                    }
                }
            }
        },
        "key2" : {
            "product": {
                "tags": {
                    "name": {
                        "_score": 500,
                        "_searchable": false
                    }
                }
            }
        }
    }
}
```
Case 2: The key does not exist

Status code: 404

Case 3: Without sending `keys` parameter, return all configurations of current logged-in user

Request:
```
[GET] /api/_info/config-me
```
## Mass Update/Insert user configuration for logged-in user
### Before
Request:
```
[POST] /api/user-config

Body

{
    "id": "43d2ba68b65e4154a38d9aa2501162e4"
    "key": "grid.setting.sw-order-list"
    "userId": "71d4deac6d7b410c982d1f1883960e25",
    "value": {},
}
```
```
[PATCH] /api/user-config/43d2ba68b65e4154a38d9aa2501162e4

Body

{
    "id": "43d2ba68b65e4154a38d9aa2501162e4"
    "value": {},
}
```

### After
Post an array of the configuration, which key of array is the key of user_config, and value of the array is the value you want to create or update. If the key exists, it will do the update action. Otherwise, it will create a new one with a given key and value

Request:
```
[POST] /api/_info/config-me

Body

{
    "key1": "value1",
    "key2": "value2" 
}
```

Response

Status code: 204
## New structure of the associationEntitiesConfig
We have added new properties to the associationEntitiesConfig to provide adding and deleting rule assignments, if wanted.
The whole structure should look like this:
```
{
    id: 'yourIdToIdentifTheData',
    notAssignedDataTotal: 0, // Total of not assigned data, this value will be automatically updated
    allowAdd: true, // Then you have to fill in the addContext
    entityName: 'yourEntityName',
    label: 'myNamespace.myLabel',
    criteria: () => { // The criteria to load the displayed data in the rule assignment
        const criteria = new Criteria();
        .....
        return criteria;
    },
    api: () => { // The context to load the data
        const api = Object.assign({}, Context.api);
        ...
        return api;
    },
    detailRoute: ...,
    gridColumns: [ // Definition of the columns in the rule assignment list
        {
            property: 'name',
            label: 'Name',
            rawData: true,
            sortable: true,
            routerLink: 'sw.product.detail.prices',
            allowEdit: false,
        },
        ...
    ],
    deleteContext: { // Configuration of the deletion
        type: 'many-to-many', // Types are many-to-many or one-to-many.
        entity: 'entityToDelete', // Entity which should be deleted / updated
        column: 'yourColumn', // Column in the entity to delete / update
    },
    addContext: { // Configuration of the addition
        type: 'many-to-many', // Types are many-to-many or one-to-many
        entity: 'entityToAdd', // Entity which should be added / updated
        column: 'yourColumn', // Column in the entity to add / update
        searchColumn: 'yourColumn', // Column which should be searchable
        criteria: () => { // Criteria to display in the add modal
            const criteria = new Criteria();
            ...
            return criteria;
        },
        gridColumns: [ // Definition of the columns in the add modal
            {
                property: 'name',
                label: 'Name',
                rawData: true,
                sortable: true,
                allowEdit: false,
            },
            ...
        ],
    },
},
```

## Extending the configuration

If you want to add a configuration or modify an existing one, you have to override the `sw-settings-rule-detail-assignments` component like this:

```
Component.override('sw-settings-rule-detail-assignments', {
    computed: {
        associationEntitiesConfig() {
            const associationEntitiesConfig = this.$super('associationEntitiesConfig');
            associationEntitiesConfig.push(...);
            return associationEntitiesConfig;
        },
    },
});
```

## Example for delete context
### One-to-many
```
deleteContext: {
    type: 'one-to-many',
    entity: 'payment_method',
    column: 'availabilityRuleId',
},
```

### Many-to-many
Important you have to add the association column to the criteria first:

```
criteria: () => {
    const criteria = new Criteria();
    criteria.setLimit(associationLimit);
    criteria.addFilter(Criteria.equals('orderRules.id', ruleId));
    criteria.addAssociation('orderRules');

    return criteria;
},
```

Then you have to use

```
deleteContext: {
    type: 'many-to-many',
    entity: 'promotion',
    column: 'orderRules',
},
```

### Deletion of extension values

If you want to delete an extension assignment, you have to include the extension path in the column value:

```
deleteContext: {
    type: 'many-to-many',
    entity: 'product',
    column: 'extensions.swagDynamicAccessRules',
},
```
## AppScripts Feature
Apps can now include scripts to run synchronous business logic inside the shopware stack.
Visit the [official documentation](https://developer.shopware.com/docs/guides/plugins/apps) for more information on that feature.
App manufacturers who add action buttons which provide feedback to the Administration are now able to access the following meta-information:

| Query parameter | Example value | Description |
|---|---|---|
| shop-id | KvhpuoEVXWmtjkQa | The ID of the shop where the action button was triggered. |
| shop-url | https://shopware.com | The URL of the shop where the action button was triggered. |
| sw-version | 6.4.7.0 | The installed Shopware version of the shop where the action button was triggered. |
| sw-context-language | 2fbb5fe2e29a4d70aa5854ce7ce3e20b | The language (UUID) of the context (`Context::getLanguageId()`). |
| sw-user-language | en-GB | The language (ISO code) of the user who triggered the action button. |
| shopware-shop-signature | `hash_hmac('sha256', $query, $shopSecret)` | The hash of the query, signed with the shop's secret. |

You **must** make sure to verify the authenticity of the incoming request by checking the `shopware-shop-signature`!
## New `--json` option for plugin list command
It is now possible to retrieve the plugin information in JSON format to easier parse it,
e.g. in deployment or other CI processes.
## Media Resolution in Themes
Media URLs are now available in the property path `$config[$key]['value']` instead of `$config['fields'][$key]['value'] = $media->getUrl();`, where for example scss expected them to be.
IPv6 URLs as file uploads are only valid in *[]* notation. See examples below:

* **Valid:** https://[2000:db8::8a2e:370:7334]
* **Valid:** https://[2000:db8::8a2e:370:7334]:80
* **Invalid:** https://2000:db8::8a2e:370:7334
* **Invalid:** https://2000:db8::8a2e:370:7334:80
The current UPGRADE.md will from now on only contain extended information on non breaking additions. All breaking changes will be explained in the `UPGRADE.md` for the next major version release. At the time of writing this will be the `UPGRADE-6.5.md`.

# 6.4.7.0
Added a new constructor argument `iterable $updateBy = []` in `Shopware\Core\Content\ImportExport\Struct\Config` which will become required starting from `v6.5.0`.

The new parameter is used to pass a mapping from an entity to a single field of the corresponding definition. This mapping is then used to resolve the primary key of a data set. This provides an alternative to using IDs for updating existing data sets.

### Before

```php
$config = new Config(
    [['key' => 'productNumber', 'mappedKey' => 'product_number']], 
    ['sourceEntity' => $sourceEntity]
);
```

### After

```php
$config = new Config(
    [['key' => 'productNumber', 'mappedKey' => 'product_number']], 
    ['sourceEntity' => $sourceEntity],
    [['entityName' => ProductDefinition::ENTITY_NAME, 'mappedKey' => 'productNumber']]
);
```
## Position constants for CMS slots

Before, the slots had no order in the form overviews of category detail or the page view of CMS templates. This was due to a lack of sort values of slots.
But now every slot type (`cms_slot.slot`) has a specific positiong value, to be found in `administration/src/module/sw-cms/constant/sw-cms.constant.js`. 
When adding own blocks with new slot templates, plugin developers should be aware of that and add their own slot position values and extend
`administration/src/module/sw-cms/component/sw-cms-page-form/index.js::slotPositions()` to get their own values into the constants.

```js
slotPositions() {
    const myPositions = {
        'my-left-top-slot': 250,
        'my-very-left-center-slot': 950
    };
    
    return {
        ...myPositions,
        ...this.$super('slotPositions'),
    };
},
```

Please be careful and chose the numbers wisely. The lower the number, the earlier the slot will appear. **Do not override existing properties to avoid side effects!**
Therefore, the "left namespace" is described by numbers intervals of 0 to 999, center 1000 to 1999, right 2000 to 2999 and everything else 3000 to 4999, with 5000 being the default value to be used when no own values are provided.
## Implement a custom increment pool
If you want to use the default `mysql` or `redis` or `array` adapter, you can ignore this tutorial and just use `type: 'mysql' // or redis, array` in the config file

It is quite easy to implement a new pool or a new adapter for the `increment` gateway.
Simply provide a service with the prefix `shopware.increment.<your_pool>.gateway.` and the `type` as suffix.
This then gives the full service id, as with the `array` type: `shopware.increment.your_pool.gateway.array`.

Enclosed is the implementation for the array adapter, which should clarify the concept. The content of the adapter has been removed for clarity:
```ArrayIncrementer.php
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

class ArrayIncrementer extends AbstractIncrementer
{
    public function getDecorated(): AbstractIncrementer { }

    public function increment(string $cluster, string $key): void { }

    public function decrement(string $cluster, string $key): void {}

    public function reset(string $cluster, ?string $key): void { }

    public function list(string $cluster, int $limit = 5, int $offset = 0): array { }
    
    public function getPool(): string
    
    public function getConfig(): array
}
```

```services.xml
<service id="shopware.increment.your_pool.gateway.array" 
         class="Shopware\Core\Framework\Increment\ArrayIncrementer"/>
```

```shopware.yaml
shopware:
    increment:
        your_pool:
            type: 'array'
```

If the custom adapter requires additional configs, they can simply be added dynamically under `shopware.increment.your_pool.config`.
```shopware.yaml
shopware:
    increment:
        your_pool:
            type: 's3'
            config: 
                secret: '..'
                url: '...'
```
The Electronic Article Number (EAN) was renamed to Global Trade Item Number (GTIN) in 2005.

# 6.4.6.0
## Rate Limiter

With 6.4.6.0 we have implemented a rate limit by default to reduce the risk of bruteforce for the following routes:
- `/store-api/account/login`
- `/store-api/account/recovery-password`
- `/store-api/order`
- `/store-api/contact-form`
- `/api/oauth/token`
- `/api/_action/user/user-recovery`

### Rate Limiter configuration

The confiuration for the rate limit can be found in the `shopware.yaml` under the map `shopware.api.rate_limiter`.
More information about the configuration can be found at the [developer documentation](https://developer.shopware.com/docs/guides/hosting/infrastructure/rate-limiter).
Below you can find an example configuration.

```yaml
shopware:
  api:
    rate_limiter:
      example_route:
        enabled: true
        policy: 'time_backoff'
        reset: '24 hours'
        limits:
          - limit: 10
            interval: '10 seconds'
          - limit: 15
            interval: '30 seconds'
          - limit: 20
            interval: '60 seconds'
```

If you plan to create your own rate limits, head over to our [developer documentation](https://developer.shopware.com/docs/guides/plugins/plugins/framework/rate-limiter/add-rate-limiter-to-api-route).
## Update `/api/_info/events.json` API
* Added `aware` property to `BusinessEventDefinition` class at `Shopware\Core\Framework\Event`.
* Deprecated `mailAware`, `logAware` and `salesChannelAware` properties in `BusinessEventDefinition` class at `Shopware\Core\Framework\Event`.
### Response of API
* Before:
```json
[
    {
        "name": "checkout.customer.before.login",
        "class": "Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent",
        "mailAware": false,
        "logAware": false,
        "data": {
            "email": {
                "type": "string"
            }
        },
        "salesChannelAware": true,
        "extensions": []
    }
]
```
* After:
```json
[
    {
        "name": "checkout.customer.before.login",
        "class": "Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent",
        "data": {
            "email": {
                "type": "string"
            }
        },
        "aware": [
            "Shopware\\Core\\Framework\\Event\\SalesChannelAware"
        ],
        "extensions": []
    }
]
```
## Added Maintenance-Bundle

A maintenance bundle was added to have one place where CLI-commands und Utils are located, that help with the ongoing maintenance of the shop.

To load enable that bundle, you should add the following line to your `/config/bundles.php` file, because from 6.5.0 onward the bundle will not be loaded automatically anymore:
```php
return [
   ...
   Shopware\Core\Maintenance\Maintenance::class => ['all' => true],
];
```
In that refactoring we moved some CLI commands into that new bundle and deprecated the old command classes. The new commands are marked as internal, as you should not rely on the PHP interface of those commands, only on the CLI API.

Additionally we've moved the `UserProvisioner` service from the `Core/System/User` namespace, to the `Core/Maintenance/User` namespace, make sure you use the service from the new location.
Before:
```php
use Shopware\Core\System\User\Service\UserProvisioner;
```
After:
```php
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
```
### Create own SeoUrl Twig Extension
Create a regular Twig extension, instead of tagging it with name `twig.extension` use tag name `shopware.seo_url.twig.extension`

Example Class:
```php
<?php declare(strict_types=1);

namespace SwagExample\Core\Content\Seo\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ExampleTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lastBigLetter', [$this, 'convert']),
        ];
    }

    public function convert(string $text): string
    {
        return strrev(ucfirst(strrev($text)));
    }
}
```

Example service.xml:
```xml
<service id="SwagExample\Core\Content\Seo\Twig\ExampleTwigFilter">
    <tag name="shopware.seo_url.twig.extension"/>
</service>
```
## Context`s properties will be natively typed
The properties of `\Shopware\Core\Framework\Context` will be natively typed in the future. 
If you extend the `Context` make sure your implementations adheres to the type constraints for the protected properties.
When you depend on a self-shipped bundle to already been loaded before your plugin, you can now use negative keys in `getAdditionalBundles` to express a different order. Use negative keys to load them before your plugin instance:

```
class AcmePlugin extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            -10 => new DependencyBundle(),
        ];
    }
}
```

# 6.4.5.0
If multiple `RetryableQuery` are used within the same SQL transaction, and a deadlock occurs, the whole transaction is
rolled back internally and can be retried. But if instead only the last `RetryableQuery` is retried this can cause all
kinds of unwanted behaviour (e.g. foreign key constraints).

With the changes to the `RetryableQuery`, you are now encouraged to pass a `Doctrine\DBAL\Connection` in the constructor
and the static `retryable` function. This way, in case of a deadlock, the `RetryableQuery` can detect an ongoing
transaction and may rethrow the error instead of retrying itself.

#### Old usages (now deprecated):
  ```php
  $retryableQuery = new RetryableQuery($query);
  
  RetryableQuery::retryable(function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```

#### New usages:
  ```php
  $retryableQuery = new RetryableQuery($connection, $query);
  
  RetryableQuery::retryable($this->connection, function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```

If you are knowingly using a SQL transaction to execute multiple statements, use the newly added `RetryableTransaction`
class. With it the whole transaction can be retried in case of a deadlock.
#### Example usage
  ```php
  RetryableTransaction::retryable($this->connection, function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```
## Deprecation of AdminOrderCartService

The `\Shopware\Administration\Service\AdminOrderCartService` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopware\Core\Checkout\Cart\ApiOrderCartService` instead. 

## Deprecation of Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent

The `\Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent` instead.

## Deprecation of Shopware\Storefront\Event\ProductExportContentTypeEvent

The `\Shopware\Storefront\Event\ProductExportContentTypeEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent` instead.

## Deprecation of Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage

The `\Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopware\Storefront\Theme\ThemeAssetPackage` instead.
## RegisterController::register

Registering a customer with `\Shopware\Storefront\Controller\RegisterController::register` now requires the request parameter `createCustomerAccount` to create a customer account.
If you dont specify this parameter a guest account will be created.
## Deprecating reading entities with the storage name of the primary key fields

When you added a custom entity definition with a combined primary key you need to pass the field names when you want to read specific entities.
The use of storage names when reading entities is deprecated by now, please use the property names instead.
The support of reading entities with the storage name of the primary keys will be removed in 6.5.0.0.

### Before
```php
new Criteria([
    [
        'storage_name_of_first_pk' => 1,
        'storage_name_of_second_pk' => 2,
    ],
]);
```

### Now
```php
new Criteria([
    [
        'propertyNameOfFirstPk' => 1,
        'propertyNameOfSecondPk' => 2,
    ],
]);
```
## StorefrontRenderEvent Changed
If you use the `StorefrontRenderEvent` you will get the original template as the `view` parameter instead of the inheriting template from v6.5.0.0
Take this in account if your subscriber depends on the inheriting template currently.
## Symfony Asset Version Strategy construction moved to dependency injection container

To be able to decorate the Symfony asset versioning easier, you can now decorate the service in the DI container instead of overwriting the service where it will be constructed.

Shopware offers by default many assets like `theme`, all those assets have an own version strategy service in the di like `shopware.asset.theme.version_strategy`

This can be decorated in the DI and the new class needs to implement the `\Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface` interface.
Here is an example to build the version strategy with the content instead of timestamps
```php
<?php declare(strict_types=1);

class Md5ContentVersionStrategy implements VersionStrategyInterface
{
    private FilesystemInterface $filesystem;

    private TagAwareAdapterInterface $cacheAdapter;

    private string $cacheTag;

    public function __construct(string $cacheTag, FilesystemInterface $filesystem, TagAwareAdapterInterface $cacheAdapter)
    {
        $this->filesystem = $filesystem;
        $this->cacheAdapter = $cacheAdapter;
        $this->cacheTag = $cacheTag;
    }

    public function getVersion(string $path)
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path)
    {
        try {
            $hash = $this->getHash($path);
        } catch (FileNotFoundException $e) {
            return $path;
        }

        return $path . '?' . $hash;
    }

    private function getHash(string $path): string
    {
        $cacheKey = 'metaDataFlySystem-' . md5($path);

        /** @var ItemInterface $item */
        $item = $this->cacheAdapter->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $hash = md5($this->filesystem->read($path));

        $item->set($hash);
        $item->tag($this->cacheTag);
        $this->cacheAdapter->saveDeferred($item);

        return $item->get();
    }
}
```

# 6.4.4.1
## Deprecating reading entities with the storage name of the primary key fields

When you added a custom entity definition with a combined primary key you need to pass the field names when you want to read specific entities.
The use of storage names when reading entities is deprecated by now, please use the property names instead.
The support of reading entities with the storage name of the primary keys will be removed in 6.5.0.0.

### Before
```php
new Criteria([
    [
        'storage_name_of_first_pk' => 1,
        'storage_name_of_second_pk' => 2,
    ],
]);
```

### Now
```php
new Criteria([
    [
        'propertyNameOfFirstPk' => 1,
        'propertyNameOfSecondPk' => 2,
    ],
]);
```

# 6.4.4.0
## Added support for building administration without database

In some setups it's common that the application is built with two steps in a `build` and `deploy` phase. The `build` process doesn't have any database connection.
Currently, Shopware needs to build the administration a database connection, to discover which plugins are active. To avoid that behaviour we have added a new `ComposerPluginLoader` which loads all information from the installed composer plugins.

To use the `ComposerPluginLoader` you have to create a file like `bin/ci` and setup the cli application with loader. There is an example:

```php
#!/usr/bin/env php
<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Production\HttpKernel;
use Shopware\Production\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;

set_time_limit(0);

$classLoader = require __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';

if (class_exists(Dotenv::class) && is_readable($envFile) && !is_dir($envFile)) {
    (new Dotenv())->usePutenv()->load($envFile);
}

if (!isset($_SERVER['PROJECT_ROOT'])) {
    $_SERVER['PROJECT_ROOT'] = dirname(__DIR__);
}

$input = new ArgvInput();
$env = $input->getParameterOption(['--env', '-e'], $_SERVER['APP_ENV'] ?? 'prod', true);
$debug = ($_SERVER['APP_DEBUG'] ?? ($env !== 'prod')) && !$input->hasParameterOption('--no-debug', true);

if ($debug) {
    umask(0000);

    if (class_exists(Debug::class)) {
        Debug::enable();
    }
}

$pluginLoader = new ComposerPluginLoader($classLoader, null);

$kernel = new HttpKernel($env, $debug, $classLoader);
$kernel->setPluginLoader($pluginLoader);

$application = new Application($kernel->getKernel());
$application->run($input);
```

With the new file we can now dump the plugins for the administration without database with the command `bin/ci bundle:dump`
## New __construct dependency

A new dependency has been added for the `EntityRepository` and the `SalesChannelEntityRepository`.
If you have defined the repository class yourself in your services.xml, you have to adapt it until 6.5 as follows:

```before
<service class="Shopware\Core\Framework\DataAbstractionLayer\EntityRepository" id="product.repository">
    <argument type="service" id="Shopware\Core\Content\Product\ProductDefinition"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\VersionManager"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface"/>
    <argument type="service" id="Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator.inner"/>
    <argument type="service" id="event_dispatcher"/>
</service>
```

Now you have to inject the `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory` service after the `event_dispatcher`
```after
<service class="Shopware\Core\Framework\DataAbstractionLayer\EntityRepository" id="product.repository">
    <argument type="service" id="Shopware\Core\Content\Product\ProductDefinition"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\VersionManager"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface"/>
    <argument type="service" id="Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator.inner"/>
    <argument type="service" id="event_dispatcher"/>
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory"/>
</service>
```
Up to 6.5, a compiler pass ensures that the event factory is injected via the `setEntityLoadedEventFactory` method.
## Compiling the Storefront theme without database

We have added a new configuration to load the theme configuration from static files instead of the database. This allows building in the CI process the entire storefront assets before deploying the application.
To enable this, create a new file `config/packages/storefront.yml` with the following content:

```yaml
storefront:
    theme:
        config_loader_id: Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader
        available_theme_provider: Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider
```

With this configuration `theme:compile` will force that the configuration will be loaded from the private filesystem. Per default the private file system writes into the `files` folder. It is highly recommended saving into an external storage like s3, to have it accessible also from the CI.
The static files can be generated using `theme:dump` (requires database access) or by changing a theme configuration option in the administration.
## Replace computed property usage
Replace `maintenanceIpWhitelist` with `maintenanceIpAllowlist`
## Change response format of searchIds when using with a mapping entity 

When using `repository.searchIds` method with a mapping entity, it now returns the primary keys pair in camelCase (property name) format instead of snake_case format (storage name).
The storage keys are kept in returned data for now for backwards compatibility but will be deprecated in the next major v6.5.0

Example response of a searchIds request with `product_category` repository:

### Before

```json
{
    "total": 1,
    "data": [
        {
            "product_id": "0f56c10f8c8e41c4acf700e64a481d86",
            "category_id": "7b57ce0d86de4b0da3004e3113b79640"
        }
    ]
}
```

### After

```json
{
    "total": 1,
    "data": [
        {
            "product_id": "0f56c10f8c8e41c4acf700e64a481d86",
            "productId": "0f56c10f8c8e41c4acf700e64a481d86",
            "category_id": "7b57ce0d86de4b0da3004e3113b79640"
            "categoryId": "7b57ce0d86de4b0da3004e3113b79640"
        }
    ]
}
```

# 6.4.3.0

## Change tax-free get and set in CountryEntity
Deprecated `taxFree` and `companyTaxFree` in `Shopware/Core/System/Country/CountryEntity`, use `customerTax` and `companyTax` instead.

## If you are writing the fields directly, the tax-free of the country will be used:
### Before
```php
$countryRepository->create([
        [
            'id' => Uuid::randomHex(),
            'taxFree' => true,
            'companyTaxFree' => true,
            ...
        ]
    ],
    $context
);
```
### After 
```php
$countryRepository->create([
        [
            'id' => Uuid::randomHex(),
            'customerTax' => [
                'enabled' => true, // enabled is taxFree value in old version
                'currencyId' => $currencyId,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true, // enabled is companyTaxFree value in old version
                'currencyId' => $currencyId,
                'amount' => 0,
            ],
            ...
        ]
    ],
    $context
);
```

## How to use the new getter and setter of tax-free in country:
### Before
* To get tax-free
```php
$country->getTaxFree();
$country->getCompanyTaxFree();
```
* To set tax-free
```php
$country->setTaxFree($isTaxFree);
$country->setCompanyTaxFree($isTaxFree);
```
### After
* To get tax-free
```php
$country->getCustomerTax()->getEnabled(); // enabled is taxFree value in old version
$country->getCompanyTax()->getEnabled(); // enabled is companyTaxFree value in old version
```
* To set tax-free
```php
// TaxFreeConfig::__construct(bool $enabled, string $currencyId, float $amount);
$country->setCusotmerTax(new TaxFreeConfig($isTaxFree, $currencyId, $amount));
$country->setCompanyTax(new TaxFreeConfig($isTaxFree, $currencyId, $amount));
```

## Update EntityIndexer implementation
Two new methods have been added to the abstract `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer`.
* `getTotal` - Shall return the number of records to be processed by the indexer on a Full index.
* `getDecorated` - Shall return the decorated service (see decoration pattern adr).

These two methods are declared as `abstract` with the 6.5.0.0. Here is an example of how a possible implementation might look like:
```

    public function getTotal(): int
    {
        return $this
            ->iteratorFactory
            ->createIterator($this->repository->getDefinition(), $offset)
            ->fetchCount();
        
        // alternate    
        return $this->connection->fetchOne('SELECT COUNT(*) FROM product');
    }

    public function getDecorated(): EntityIndexer
    {
        // if you implement an own indexer
        throw new DecorationPatternException(self::class);
        
        // if you decorate a core indexer
        return $this->decorated;
    }

```

## Storefront Controller need to have Twig injected in future versions

The `twig` service will be private with upcoming Symfony 6.0. To resolve this deprecation, a new method `setTwig` was added to the `StorefrontController`.
All controllers which extends from `StorefrontController` need to call this method in the dependency injection definition file (services.xml) to set the Twig instance.
The controllers will work like before until the Symfony 6.0 update will be done, but they will create a deprecation message on each usage.
Below is an example how to add a method call for the service using the XML definition.

### Before

```xml
<service id="Shopware\Storefront\Controller\AccountPaymentController">
    <call method="setContainer">
        <argument type="service" id="service_container"/>
    </call>
</service>
```

### After

```xml
<service id="Shopware\Storefront\Controller\AccountPaymentController">
    <call method="setContainer">
        <argument type="service" id="service_container"/>
    </call>
    <call method="setTwig">
        <argument type="service" id="twig"/>
    </call>
</service>
```

## ListField strict mode
A `ListField` will now always return a non associative array if the strict mode is true. This will be the default in 6.5.0. Please ensure that the data is saved as non associative array or switch to `JsonField` instead.

Valid `listField` before: 
```
Array
(
    [0] => baz
    [foo] => bar
    [1] => Array
        (
            [foo2] => Array
                (
                    [foo3] => bar2
                )
        )
)
```

Valid `ListField` after:
```
Array
(
    [0] => baz
    [1] => bar
    [2] => Array
        (
            [foo2] => Array
                (
                    [foo3] => bar2
                )
        )
)
```

## Deprecated of case-insensitive annotation parsing

With Shopware 6.5.0.0 the annotation parsing will be case-sensitive.
Make sure to check that all your annotation properties fit their respective name case.
E.g.: In case of the `Route` annotation you can have a look into the name case of the constructor parameters of the `\Symfony\Component\Routing\Annotation\Route` class.

Before:

```
@Route("/", name="frontend.home.page", Options={"seo"="true"}, Methods={"GET"})
```

After:

```
@Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"})
```


# 6.4.2.0

## New Captcha Solution
* We deprecated the system config `core.basicInformation.activeCaptchas` with only honeypot captcha and upgraded to system config `core.basicInformation.activeCaptchasV2` with honeypot, basic captcha, Google reCaptcha v2, Google reCaptcha v3
### Setting captcha in administration basic information
* Honeypot captcha is activated by default
* Select to active more basic captcha, Google reCaptcha
* With Google reCaptcha v2 checkbox:
  Configure the correct site key and secret key for reCaptcha v2 checkbox
  Turn off option `Invisible Google reCAPTCHA v2`
* With Google reCaptcha v2 invisible:
  Configure the correct site key and secret key for reCaptcha v2 invisible
  Turn on option `Invisible Google reCAPTCHA v2`
* With Google reCaptcha v3:
  Configure the correct site key and secret key for reCaptcha v3
  Configure `Google reCAPTCHA v3 threshold score`, default by 0.5
### How to adapt the captcha solution upgrade?
* Add `Shopware\Storefront\Framework\Captcha\Annotation\Captcha` annotation to StorefrontController-Routes to apply captcha protection.
* Due to captcha forms will be displayed when activated, be aware that the captcha input might break your layout
#### Before
```php
{% sw_include '@Storefront/storefront/component/captcha/base.html.twig' with { captchas: config('core.basicInformation.activeCaptchas') } %}
```
#### After
```php
{% sw_include '@Storefront/storefront/component/captcha/base.html.twig'
    with {
        additionalClass : string,
        formId: string,
        preCheck: boolean
    }
%}
```

We have a default captchas config, so now you don't need to provide a captchas parameter to the component, if you provide the captchas parameter, they will be overridden

Options:
- `additionalClass`: (optional) default is `col-md-6`,
- `formId`: (optional) - you can add the custom `formId`,
- `preCheck`: (optional) default is `false` - if true it will call an ajax-route to pre-validate the captcha, before the form is submitted. When using a native form, instead of an ajax-form, the `precheck` should be `true`.


# 6.4.1.0

## Default messenger routing

We've removed the default routing rules, because it made it impossible to send a messages to transports without also 
sending it to the default transport.

This is now handled by `DefaultSenderLocator` which sends it to the `messenger.default_transport` only if no
routing rule has matched and no sender was found.

### Usage of parameter exclusion list

There are certain use cases where a GET parameter has a new value for every request, generating a new entry in the HTTP cache every time. An example would be the Google Adwords ClickId parameter `gclid` which contains a new id for every click that was generated by Google Adwords. This leads to a bad performance for the visitor since existing caches aren't being used. This allows the caching system to be more efficient.

Storefront configuration provides a list of known parameters that fall in this category. You can overwrite or extend this list in your [bundle configuration](https://developer.shopware.com/docs/v/v6.4.0/guides/hosting/infrastructure/filesystem#configuration).   
```
storefront:
    http_cache:
        ignored_url_parameters:
            - 'pk_campaign' # Piwik
            - 'piwik_campaign'
            - 'pk_kwd'
            - 'piwik_kwd'
            - 'pk_keyword'
            - 'mtm_campaign' # Matomo
            - 'matomo_campaign'
            - 'mtm_cid'
            - 'matomo_cid'
            - 'mtm_kwd'
            - 'matomo_kwd'
            - 'mtm_keyword'
            - 'matomo_keyword'
            - 'mtm_source'
            - 'matomo_source'
            - 'mtm_medium'
            - 'matomo_medium'
            - 'mtm_content'
            - 'matomo_content'
            - 'mtm_group'
            - 'matomo_group'
            - 'mtm_placement'
            - 'matomo_placement'
            - 'pixelId' # Yahoo
            - 'kwid'
            - 'kw'
            - 'chl'
            - 'dv'
            - 'nk'
            - 'pa'
            - 'camid'
            - 'adgid'
            - 'utm_term' # Google
            - 'utm_source'
            - 'utm_medium'
            - 'utm_campaign'
            - 'utm_content'
            - 'cx'
            - 'ie'
            - 'cof'
            - 'siteurl'
            - '_ga'
            - 'adgroupid'
            - 'campaignid'
            - 'adid'
            - 'gclsrc' # Google DoubleClick
            - 'gclid'
            - 'fbclid' # Facebook
            - 'fb_action_ids'
            - 'fb_action_types'
            - 'fb_source'
            - 'mc_cid' # Mailchimp
            - 'mc_eid'
            - '_bta_tid' # Bronto
            - '_bta_c'
            - 'trk_contact' # Listrak
            - 'trk_msg'
            - 'trk_module'
            - 'trk_sid'
            - 'gdfms'  # GodataFeed
            - 'gdftrk'
            - 'gdffi'
            - '_ke'  # Klaviyo
            - 'redirect_log_mongo_id' # Klaviyo
            - 'redirect_mongo_id'
            - 'sb_referer_host'
            - 'mkwid' # Marin
            - 'pcrid'
            - 'ef_id' # Adobe Advertising Cloud
            - 's_kwcid' # Adobe Analytics
            - 'msclkid' # Microsoft Advertising
            - 'dm_i' # dotdigital
            - 'epik' # Pinterest
            - 'pp'
```

# 6.4.0.0

## Breaking changes
For a complete list of breaking changes please refer to the [bc changelog](/changelog/release-6-4-0-0/2021-03-18-6.4-breaking-changes.md) changelog file.

---

## Minimum PHP version increased to 7.4
The minimum required PHP version for Shopware 6.4.0.0 is now PHP 7.4.
Please make sure, that your system has at least this PHP version activated.

We've also added support for PHP 8.0. While Shopware is de-facto ready for PHP 8.0,
some dependencies do not support PHP 8.0 in their `composer.json` in theory.
Until these dependencies add official PHP 8.0 support in their `composer.json`, 
we decided to set the `config.platform.php` of the development root composer.json to `7.4.0`.
This is to prevent composer failing to update dependencies, because of PHP version constraints.

---

## Sodium is now a requirement
The PHP extension `sodium` is now a requirement for Shopware 6.4.0.0.

---

## Composer 2
With Shopware 6.4 we are now requiring the `composer-runtime-api` with version 2.0.
These means that Shopware is now only installable with Composer 2.
Installation with Composer 1 is no longer possible and supported.

---

## Symfony 5
Symfony was upgraded to 5.2.x. It is now locked to the minor 5.2 version.

---

## API versioning change
Corresponding to the semantic versioning strategy of Shopware, we changed the API versioning to match the major versions of Shopware. 
As the API stays backward compatible for the life cycle of the whole major version, there is no need for a separate versioning. 
Therefore we also removed the unnecessary version from the URL pattern.

```
- /[store-]api/v{version}/...
+ /[store-]api/...
```

### Upgrade flow for external API services
With Shopware **6.3.5.0** we already made the new URL pattern available as an additional alias. 
This enables you to test your application with the new URL pattern within the 6.3 major cycle, before updating to the 6.4 version.

### Detecting the current used Shopware / API
Of course, it is still important for an external service to know, which version of Shopware is used. 
Therefore we added a new information endpoint, which provides this information. 
The new endpoint is also available with Shopware **6.3.5.0**, so you can switch to this pattern in the 6.3 major cycle.

```http request
GET /api/_info/version
```

### API expectations
To have the version within the URL pattern offered the advantage of telling Shopware which version requirement you expect with the request. 
To still fulfill this need, we extended the possibilities even further. 
You can send additional expectations via headers within your request, which is not only limited to the version.

```http request
GET /api/test
sw-expect-packages: shopware/core:~6.4,swag/paypal:*
```

This example expects that the Shopware version is at least 6.4, and the PayPal extension is installed in any version. 
If the conditions are not met, the backend will respond with a 417 HTTP error.

### Since flag on entities / fields
During the life cycle of a major version, there still might be non-breaking changes to the API. 
To make this information available, every new field will have a `since` flag, which indicates, when the new field was added to the API. 
All new fields will be included in the response. 
You can still remove unwanted fields from the response by using the `includes` property to [reduce the output](https://shopware.gitbook.io/docs/guides/integrations-api/general-concepts/search-criteria#includes-apialias).

The information is shown in the Swagger documentation, in the description of the route in the schema if the request / response.

```http request
/api/_info/swagger.html
```

Also deprecated fields, which will be removed with the next major version, are marked in the schema with a `deprecated` flag. 
This enables you to react to upcoming changes as early as possible.

---

## Currency filter
The default of the currency filter in the administration changed.
It will now display by default 2 fraction digits and up to 20, if available.

### Before
* value is 15.123456 -> output is 15.12
* value is 15.12345678913245 -> output is 15.12

### After
* value is 15.123456 -> output is 15.123456
* value is 15.12345678913245 -> output is 15.12345678913245
  In case you've been creating empty feature sets using the current faulty behaviour, please make sure to at least include
  a name for any new feature set from now on.
  System and plugin configurations made with `config.xml` can now have less than 4
  characters as configuration key but are not allowed anymore to start with a
  number.

---

## References to assets inside themes

We've deprecated `$asset-path` because it was generated at compilation time and contained the `APP_URL` by default,
which should only be relevant to administration. Instead, `$app-css-relative-asset-path` is to be used, which is an
url that is relative to the `app.css` that points to the asset folder.

As a side effect, the fonts are now loaded from the theme folder instead of the bundle asset folder. This should work out of the box,
because all assets of the theme are also copied into the theme folder.

---

## Removed plugin manager
The plugin manager in the administration is removed with all of its components and replaced by the `sw-extension` module.
The controller for the plugin manager with all of its routes is removed and replaced by the `ExtensionStoreActionsController`.

---

## New ApiAware flag
The new `ApiAware` flag replaces the current `ReadProtected` flag for entity definitions.
See [NEXT-13371 - Added api aware flag](/changelog/release-6-3-5-1/2021-01-25-added-api-aware-flag.md)

---

## OAuth2 upgrade
The `league/oauth2-server` and `lcobucci/jwt` dependencies were upgraded to their next respective major versions.
This comes with a break in our current oauth2 core implementation.

See [the commit on GitHub](https://github.com/shopware/platform/commit/656c82d5232c87b75e1d6b42bd6493d674807791) for details.

---

## Changed the loading of storefront SCSS files in extensions
Previously all Storefront relevant SCSS files (`*.scss`) of an extension have automatically been loaded and compiled by shopware when placed inside the directory `src/Resources/app/storefront/src/scss`.
Because all SCSS files have been loaded automatically it could have let to inconsistent results when dealing with custom SCSS variables in separate files for example.

This behaviour has been changed and now only a single entry file will be used by extensions which is the `YourPlugin/src/Resources/app/storefront/src/scss/base.scss` or `YourApp/Resources/app/storefront/src/scss/base.scss`.

### Before

All the SCSS files in this example directory have been loaded automatically:

```
└── scss
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

### After

Now you need a `base.scss` and need to load all other files from there using the `@import` rule:

```
└── scss
    ├── base.scss <-- This is now mandatory and loads all other files
    ├── custom-component.scss
    ├── footer.scss
    ├── header.scss
    ├── product-detail.scss
    └── variables.scss
```

The `base.scss` for the previous example directory would look like this in order to load all SCSS properly:

```scss
// Content of the base.scss
@import 'variables';
@import 'header';
@import 'product-detail';
@import 'custom-component';
```

---

## Swift_Mailer exchanged with Symfony/Mailer
The current default mailer `Swift_Mailer` was exchanged with `Symfony\Mailer`. 
If you have configured the Mailer using the environment variable `MAILER_URL`, you have to change this to the new syntax.
Refer to the [Symfony Documentation](https://symfony.com/doc/current/mailer.html#using-built-in-transports) for the updated syntax.

---

## context.salesChannel.countries removed
Previously, the sales channel object in the context contained all countries assigned to the sales channel. This data has now been removed. 
The access via `$context->getSalesChannel()->getCountries()` therefore no longer returns the previous result.
To load the countries of a sales channel, the class `\Shopware\Core\System\Country\SalesChannel\CountryRoute` should be used.

---

## Separated plugin download and update
If you're using the `api.custom.store.download` route, be aware that its behaviour was changed.
The route will no longer trigger a plugin update.
In case you'd like to trigger a plugin update, you'll need to dispatch another request to the
`api.action.plugin.update` route.

---

## Removed deprecated database columns
* Removed the column `currency`.`decimal_precision`. The rounding is now controlled by the `CashRoundingConfig` in the `SalesChannelContext`
* Removed the column `product`.`purchase_price`. Replaced by `product`.`purchase_prices`
* Removed the column `customer_wishlist_product`. This column was never used, and the feature still requires a feature flag.

---

## Migration system upgrade guide

We've grouped the migrations into major versions. By default, all non-destructive migrations are executed up to the current major. 
In contrast, all destructive migrations are executed up to a "safe" point. This can be configured with the mode.

There are three possible values for the mode:
1. `--version-selection-mode=safe`: Execute only "safe" destructive changes. This means only migrations from the penultimate major are executed. 
   So with the update to 6.5.0.0 all destructive changes in 6.3 or lower are executed.
2. `--version-selection-mode=blue-green`: Execute as early as possible, but still blue-green compatible. 
   This means with the update to 6.4.1.0 from 6.4.0.0 all destructive changes in 6.3 or lower are executed.
3. `--version-selection-mode=all`: Execute all destructive changes up to the current major.

To allow this selection, we've moved all migrations from `\Shopware\Core\Framework\Migration\MigrationSource.core` 
into `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_3`. `core` is now empty by default. You can still extend it. 
The execution order is now like this:
1. `core.V6_3`
2. `core.V6_4`
3. newer majors...
4. `core`

This means all new migrations need to be created in the matching major folder. Currently, this is `src/Core/Migration/V6_3`, 
it will be `src/Core/Migration/V6_4` soon. To keep the backwards compatibility, Migrations still need to be defined in `src/Core/Migration`. 
To accomplish that, just create it in the versioned folder and create a class in the old folder that simply extends the other class without changing anything.  

The method `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion` will return a collection with all "safe" `MigrationSource`s including `core`.

**bin/console database:migrate --all core**

Should do the sames as before. Alternatively, you can run it for each major:
- `bin/console database:migrate --all V6_3`
- `bin/console database:migrate --all V6_4`
- etc

**bin/console database:migrate-destructive --all core**

This behavior changed! By default, this will only executes "safe" destructive changes. It used to execute all destructive migrations.

To get the old behavior, run:

`bin/console database:migrate-destructive --all --version-selection-mode=all core`

To run the destructive migrations as early as possible but still blue-green, run:

`bin/console database:migrate-destructive --all --version-selection-mode=blue-green core`

**Changes to auto updater**

We've changed the updater to only execute safe destructive migrations. It used to execute **ALL** destructive changes.

### Creating core migrations

To allow implementing this feature with a feature flag, we've to create a legacy migration in `src/Core/Migraiton`, 
which simply extends from the real migration in `src/Core/Migration/$MAJOR`. All migrations have been changed in that way.
The `bin/console database:create-migration` command automatically creates a legacy migration.
Due to the way that `img`'s `object-fit` works, it is not possible to mimic the 'Auto' setting of the block background. This means that elements that currently have 'Auto' set as their background mode will look different.

---

## CMS entities version aware

This change update the primary key of `cms_page`, `cms_slot`, `cms_block` and `cms_section` and the corresponding translation tables. If your plugin incorporates foreign keys to these tables you will need to update your migrations and dal entity definitions.

Please use `bin/console dal:validate` to see if you have to adjust your plugins anywhere.

### Update

If your plugin is already installed the shopware core migration will take care of adjusting the foreign key.
A new column `{TABLE_NAME}_version_id` is created, and the constraint widened. 
You will just have to add a version reference field in your definitions.

For a `cms_page` relation this would make these lines mandatory in your field definition like this:

```php
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;

new ReferenceVersionField(CmsPageDefinition::class);
```

### Install

If your plugin is newly installed you should add a combined foreign key to your `CREATE TABLE` statement.

```sql
CREATE TABLE _TABLE_ IF NOT EXISTS
    `cms_page_id` binary(16) DEFAULT NULL, # the existing column
    `cms_page_version_id` binary(16) NOT NULL, # from now on mandatory
    # [...]
    KEY `_NAME_` (`cms_page_id`,`cms_page_version_id`),
    CONSTRAINT `_NAME_` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE # notice the two column on two column key
);
```

---

### Deployment notice
Due to the migration changing the product table as well, the update process might be slower than usual.

---

## System-Config
Removed default for `detail.showReviews`, use `core.listing.showReview` instead.

---

## Guzzle major version upgrade
We upgraded the guzzle dependency to a new major version v7. Please refer to the [guzzle upgrade guide](https://github.com/guzzle/guzzle/blob/master/UPGRADING.md#60-to-70) to make sure your plugins are compatible.

---

## Elasticsearch Refactoring
To improve the performance and reliability of Elasticsearch, we have decided to refactor Elasticsearch in the first iteration in the Storefront only for Product listing and Product searches.
This allows us to create a optimized Elasticsearch index with only required fields selected by an single sql to make the indexing fast as possible.
This also means for extensions, they need to extend the indexing to make their fields searchable.

Here is an simple decoration to add a new random field named `myNewField` to the index.
For adding more information from the Database you should execute a single query with all document ids (`array_column($documents, 'id'')`) and map the values

```xml
<service id="MyDecorator" decorates="Shopware\Elasticsearch\Product\ElasticsearchProductDefinition">
    <argument type="service" id="MyDecorator.inner"/>
    <argument type="service" id="\Doctrine\DBAL\Connection"/>
</service>
```

```php
<?php

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Doctrine\DBAL\Connection;

class MyDecorator extends AbstractElasticsearchDefinition
{
    private AbstractElasticsearchDefinition $productDefinition;
    private Connection $connection;

    public function __construct(AbstractElasticsearchDefinition $productDefinition, Connection $connection)
    {
        $this->productDefinition = $productDefinition;
        $this->connection = $connection;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->productDefinition->getEntityDefinition();
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->productDefinition->getMapping($context);

        $mapping['properties']['myNewField'] = EntityMapper::INT_FIELD;

        // Adding nested field with id
        $mapping['properties']['myManyToManyAssociation'] = [
            'type' => 'nested',
            'properties' => [
                'id' => EntityMapper::KEYWORD_FIELD,
            ],
        ];

        return $mapping;
    }

    public function extendDocuments(array $documents, Context $context): array
    {
        $documents = $this->productDefinition->extendDocuments($documents, $context);
        $productIds = array_column($documents, 'id');

        $query = <<<'SQL'
SELECT LOWER(HEX(mytable.product_id)) as id, GROUP_CONCAT(LOWER(HEX(mytable.myFkField)) SEPARATOR "|") as relationIds
FROM mytable
WHERE
    mytable.product_id IN(:ids) AND
    mytable.product_version_id = :liveVersion
SQL;


        $associationData = $this->connection->fetchAllKeyValue(
            $query,
            [
                'ids' => Uuid::fromHexToBytesList($productIds),
                'liveVersion' => Defaults::LIVE_VERSION
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY
            ]
        );

        foreach ($documents as &$document) {
            // Normal field directly on the product
            $document['myNewField'] = random_int(PHP_INT_MIN, PHP_INT_MAX);

            // Nested object with an id field
            $document['myManyToManyAssociation'] = array_map(function (string $id) {
                return ['id' => $id];
            }, array_filter(explode('|', $associationData[$document['id']] ?? '')));
        }

        return $documents;
    }
}
```

When searching products make sure you add elasticsearch aware to your criteria to use Elasticsearch in background.

```php
$criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
$context = \Shopware\Core\Framework\Context::createDefaultContext();
// Enables elasticsearch for this search
$context->addState(\Shopware\Core\Framework\Context::STATE_ELASTICSEARCH_AWARE);

$repository->search($criteria, $context);
```

---

## LineItems rules behaviour changed
The rules for line items are now considering also nested line items.
Before the change, only the first level of line items was taken into account.
Check your rules, if they still take effect as intended.

---

## TreeUpdater scaling

We've replaced `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::update` with `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::batchUpdate`,
because `update` scaled badly with the tree depth. The new method takes an array instead of a single id.

---

## EntityWriteGatewayInterface

We've added the new method `prefetchExistences` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface`.
The method is optional, and a valid implementation is to not prefetch anything. The method was added to allow fetching the existence of more than one primary key at once.

---

## FieldSerializerInterface::normalize

We've added the new method `normalize` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface`.
A valid implementation is to just return `$data`. The `AbstractFieldSerializer` does that already.
The method should normalize the `$data` if it makes sense. For example, the core serializers do the following in the normalize step:
- generate missing ids (`IdField`)
- resolve foreign keys (`FkField` and `Association`)
- normalize structure for example for translations (there are multiple ways to define them)
- collect primary keys in `PrimaryKeyBag`

---

## Events

All events that are dispatched in a sales channel context now implement `ShopwareSalesChannelEvent`. The return type `getContext` may have changed from `SalesChannelContext`
to `Context`. To get the sales channel context, use `getSalesChannelContext`.

---

## Cheapest price implementation
We've added a new implementation to calculate product prices. `product.cheapestPrice` replaces `product.listingPrices`.
Update your queries accordingly.

---

## BlacklistRuleField / WhitelistRuleField dropped
The `BlacklistRuleField` and `WhitelistRuleField` implementations were dropped.
Create an own many-to-many association to achieve this functionality

---

## DAL cache removed
The DAL cache was removed. 
Therefore, calling `Shopware\Core\Framework\Context::disableCache` has no more effect.

---

## ExecuteQuery throws execption on write operations
The following SQL operations will throw an exception, if called with `Doctrine\DBAL\Connection\::executeQuery`:
`UPDATE`, `ALTER`, `BACKUP`, `CREATE`, `DELETE`, `DROP`, `EXEC`, `INSERT`, `TRUNCATE`

---

## AntiJoinFilter removed
We've enhanced the DAL to automatically detect a pattern following the `AntiJoinFilter`. 
Therefore, it was removed.

---

## ProductPriceDefinitionBuilder replaced
We've replaced the `ProductPriceDefinitionBuilder` by the `ProductPriceCalculator`

---

## Routing changes
Removed the parameter `swagShopId` from `StorefrontRenderEvent`, Use `appShopId` instead.

---

## Payment / Shipping method selection modal removed
The modal to select payment or shipping methods was removed entirely.
Instead, the payment and shipping methods will be shown instantly up to a default maximum of `5` methods.
All other methods will be hidden inside a JavaScript controlled collapse.

The changes especially apply to the `confirm checkout` and `edit order` pages.

We refactored most of the payment and shipping method storefront templates and split the content up into multiple templates to raise the usability.

---

## Datepicker component
According to document of flatpickr (https://flatpickr.js.org/formatting/), ISO Date format is now supported for the datepicker component.
With `datetime-local` dateType, the datepicker will display the user's browser time.
The value will be converted to UTC value.

### Before
* Both dateType `datetime` and `datetime-local` use UTC timezone `(GMT+00:00)`.
* If user selects date `2021-03-22` and time `12:30`, the output is `2021-03-22T12:30:000+00:00`.

### After
* With dateType `datetime`, user selects date `2021-03-22` and time `12:30`, the output is `2021-03-22T12:30:000+00:00`.
* With dateType `datetime-local`, user selects date `2021-03-22` and time `12:30` and timezone of user is `GMT+07:00`, the output is `2021-03-22T05:30.00.000Z`.

---

## Removed deprecated SCSS color variables
We removed the following deprecated color / gradient variables:

```scss
$color-biscay
$color-cadet-blue
$color-crimson
$color-contrast
$color-deep-cove
$color-emerald
$color-gray
$color-gutenberg
$color-kashmir
$color-iron
$color-light-gray
$color-link-water
$color-pumpkin-spice
$color-purple
$color-shopware-blue
$color-steam-cloud

$color-gradient-dark-gray-start
$color-gradient-dark-gray-end
$gradient-dark-gray
```
---

## NPM package copy-webpack-plugin update
This package has now version `6.4.1`, take a look at the [github changelog](https://github.com/webpack-contrib/copy-webpack-plugin/releases/tag/v6.0.0) for breaking changes.

---

## NPM package node-sass replacement
Removed `node-sass` package because it is deprecated. Added the `sass` package as replacement. For more information take a look [deprecation page](https://sass-lang.com/blog/libsass-is-deprecated).

---

## Twig system config /theme access
The `shopware.config` variable was removed. To access a system config value inside twig, use `config('my_config_key')`.
The `shopware.theme` variable was removed. To access the theme config value inside twig, use `theme_config('my_config_key')`.

---

## Changed product streams from blacklist to allowed list
The properties in the product stream are now defined with a allowed list. This can cause some missing fields which were available in earlier releases. If you need them also in feature releases then you can add them to the allowed list with an plugin.

Just use the method `addToEntityAllowList` or `addToGeneralAllowList` from the productStreamConditionService. 

Example:
```js
Shopware.Service('productStreamConditionService').addToEntityAllowList('product', 'yourProperty');
```

## Added rawTotal as required param to CartPriceField
This value is now required when creating an order through the API. The value contains the unrounded total value.
The "price" part of your json to create an order should include this.
Example:

Before:
```json
"price": {
  "totalPrice": 119.95,
  "calculatedTaxes": [
    {
      "taxRate": 21,
      "price": 119.95,
      "tax": 20.82
    }
  ],
  "positionPrice": 119.95,
  "taxRules": [
    {
      "taxRate": 21,
      "percentage": 100
    }
  ],
  "netPrice": 99.13,
  "taxStatus": "gross"
},
```

After:
```json
"price": {
  "totalPrice": 119.95,
  "calculatedTaxes": [
    {
      "taxRate": 21,
      "price": 119.95,
      "tax": 20.82
    }
  ],
  "positionPrice": 119.95,
  "taxRules": [
    {
      "taxRate": 21,
      "percentage": 100
    }
  ],
  "netPrice": 99.13,
  "taxStatus": "gross",
  "rawTotal": 119,95
},
```
