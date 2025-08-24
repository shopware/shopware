---
title: Implement Service Subscriber pattern for Storefront controllers
issue: NEXT-3462
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Storefront
* Changed `Shopware\Storefront\Controller\StorefrontController` to implement `Symfony\Contracts\Service\ServiceSubscriberInterface`
* Added autoconfigure and autowire support for modern controller configuration in `services_controller.xml`
* Added `Shopware\Storefront\DependencyInjection\Compiler\StorefrontControllerCompilerPass` for backward compatibility
* Added `Shopware\Storefront\Controller\DeprecatedContainerAccessTrait` for gradual migration
* Changed container injection from `service_container` to `Psr\Container\ContainerInterface` service locator
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

### Service Configuration Options

#### Option 1: Modern Autoconfigure (Recommended)
```xml
<services>
    <defaults public="true" autoconfigure="true" autowire="true" />
    
    <service id="MyPlugin\Controller\MyController">
        <!-- Only specific dependencies needed -->
        <argument type="service" id="my.specific.service"/>
    </service>
</services>
```

#### Option 2: Manual Configuration (Legacy)
```xml
<service id="MyPlugin\Controller\MyController">
    <argument type="service" id="my.specific.service"/>
    <tag name="controller.service_arguments"/>
    <tag name="container.service_subscriber"/>
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

- Existing controller configurations continue to work through the compiler pass
- Direct container access works with deprecation warnings until 6.9.0
- Both autoconfigure and manual configuration are supported
