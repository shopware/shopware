[titleEn]: <>(Using setter injection in plugins)
[metaDescriptionEn]: <>(This HowTo will show you how to use setting injection in your plugin class. This allows using private and your own services in the activate and deactivate method.)
[hash]: <>(article:how_to_setter_injection)

## Overview

Sometimes you need to do complex initialization operations in your activate method.
Most shopware services are private, so you cannot get them directly from the container.
To inject them into your `Plugin` class, you have to use setter injection.

## Automatic setter injection

You can benefit from autowiring simply by adding a `required` annotation to your setters.
Plugins definitions are always registered as public and with autowire in the container.

For example:

```php
<?php

namespace Swag\SetterInjection;


use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Storefront\Theme\ThemeService;

class SetterInjection extends Plugin
{
    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @required
     */
    function setThemeService(ThemeService $themeService): void
    {
        $this->themeService = $themeService;
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        // use $this->themeService
    }

    /**
     * Not automatically injected!
     */
    public function setProductRepository(EntityRepositoryInterface $productRepository): void
    {
        $this->productRepository = $productRepository;
    }
}
```

## Manual setter injection

If you want more control about how your service is injected, you can do it manually, by using your services.xml.
The automatic injection is still happening.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\SetterInjection\SetterInjection">
            <call method="setProductRepository">
                <argument type="service" id="product.repository"/>
            </call>
        </service>
    </services>
</container>
```
