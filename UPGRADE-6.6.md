# 6.6.0.0
## Introduced in 6.5.0.0
## Custom fields in cart removed:
* Add custom fields to custom field allow list in CartBeforeSerializationEvent if you need them in cart. Custom fields used in cart rules will not be removed by default.
## Create new shipping method
When you create a new shipping method, the default value for the active flag is false, i.e. the method is inactive after saving. 
Please provide the active value if you create shipping methods over the API.
## Remove static address formatting:
* Deprecated fixed address formatting, use `@Framework/snippets/render.html.twig` instead, applied on:
  - `src/Storefront/Resources/views/storefront/component/address/address.html.twig`
  - `src/Core/Framework/Resources/views/documents/delivery_note.html.twig`
  - `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`
## Remove "marc1706/fast-image-size" dependency

The dependency on the "marc1706/fast-image-size" library was removed, require the library yourself if you need it.
## Deprecated action:
* action `setAppModules` in `src/app/state/shopware-apps.store.ts` will be removed
* action `setAppModules` in `src/app/state/shopware-apps.store.ts` will be removed
## Removed `SyncOperationResult`
The `\Shopware\Core\Framework\Api\Sync\SyncOperationResult` class was removed without replacement, as it was unused.
## Removal of `MessageSubscriberInterface` for `ScheduledTaskHandler`
The method `getHandledMessages()` in abstract class `\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler` was removed, please use the `#[AsMessageHandler]` attribute instead.

Before:
```php
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }
    
    public function run(): void
    {
        // ...
    }
}
```

After: 
```php
#[AsMessageHandler(handles: MyMessage::class)]
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        // ...
    }
}
```
## Deprecated component `sw-dashboard-external-link` has been removed
* Use component `sw-external-link` instead of `sw-dashboard-external-link`
## Selector to open an ajax modal
The selector to initialize the `AjaxModal` plugin will be changed to not interfere with Bootstrap defaults data-attribute API.

### Before
```html
<a data-bs-toggle="modal" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

### After
```html
<a data-ajax-modal="true" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```
## `IsNewCustomerRule` to be removed with major release v6.6.0
* Use `DaysSinceFirstLoginRule` instead with operator `=` and `daysPassed` of `0` to achieve identical behavior
## Seeding mechanism for `AbstractThemePathBuilder`

The `generateNewPath()` and `saveSeed()` methods  in `\Shopware\Storefront\Theme\AbstractThemePathBuilder` are now abstract, this means you should implement those methods to allow atomic theme compilations.

For more details refer to the corresponding [ADR](../../adr/storefront/2023-01-10-atomic-theme-compilation.md).

