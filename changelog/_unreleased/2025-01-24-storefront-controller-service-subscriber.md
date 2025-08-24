---
title: Implement Service Subscriber pattern for Storefront controllers
issue: NEXT-3462
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Storefront
* Changed `Shopware\Storefront\Controller\StorefrontController` to implement `Symfony\Contracts\Service\ServiceSubscriberInterface`
* Changed `getSubscribedServices()` method to declare all framework services with optional markers (`?` prefix)
* Added service locator pattern for lazy-loading of services in controllers
* Added `Shopware\Storefront\Controller\DeprecatedContainerAccessTrait` for backward compatibility
* Changed all 30 controller service definitions in `controller.xml` to use service subscriber pattern
* Added `controller.service_arguments` and `container.service_subscriber` tags to controller services
* Changed container injection from `service_container` to `Psr\Container\ContainerInterface`
* Deprecated direct container access via `$this->container->get()` without service subscription
___
# Upgrade Information
## Service Subscriber Pattern for Storefront Controllers

Storefront controllers now use Symfony's ServiceSubscriberInterface for improved performance and memory efficiency. Services are lazy-loaded through a service locator instead of injecting the full container.

### Migration for Custom Controllers

If you have controllers extending `StorefrontController`, declare additional service dependencies:

#### Before
```php
class MyController extends StorefrontController
{
    public function myAction(): Response
    {
        // Direct container access - still works but deprecated
        $myService = $this->container->get('my.custom.service');
        return $this->renderStorefront('...');
    }
}
```

#### After
```php
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class MyController extends StorefrontController implements ServiceSubscriberInterface
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'my.custom.service' => '?App\Service\MyCustomService',
        ]);
    }
    
    public function myAction(): Response
    {
        // Service properly declared and lazy-loaded
        $myService = $this->container->get('my.custom.service');
        return $this->renderStorefront('...');
    }
}
```

### Service Configuration Update

Update your controller service definitions:

```xml
<service id="MyPlugin\Controller\MyController">
    <!-- Constructor arguments for specific dependencies -->
    <argument type="service" id="my.specific.service"/>
    
    <!-- Add these tags for service subscription -->
    <tag name="controller.service_arguments"/>
    <tag name="container.service_subscriber"/>
    
    <!-- Use service locator instead of full container -->
    <call method="setContainer">
        <argument type="service" id="Psr\Container\ContainerInterface"/>
    </call>
</service>
```

### Pre-Subscribed Services

The following services are already available in `StorefrontController`:
- `twig` (`Twig\Environment`)
- `event_dispatcher` (`Symfony\Contracts\EventDispatcher\EventDispatcherInterface`)
- `translator` (`Symfony\Contracts\Translation\TranslatorInterface`)
- `router` (`Symfony\Component\Routing\RouterInterface`)
- `request_stack` (`Symfony\Component\HttpFoundation\RequestStack`)
- `Shopware\Core\System\SystemConfig\SystemConfigService`
- `Shopware\Core\Framework\Adapter\Twig\TemplateFinder`
- `Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface`
- `Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface`
- `Shopware\Core\Framework\Script\Execution\ScriptExecutor`
- `Shopware\Core\Framework\Routing\RequestTransformerInterface`

### Backward Compatibility

- Direct container access continues to work with deprecation warnings
- Use `DeprecatedContainerAccessTrait` for gradual migration
