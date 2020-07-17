[titleEn]: <>(Context and scope)
[hash]: <>(article:context)

Shopware 6 preprocesses some user-, application- or environment specific information. This data is wrapped into the different context objects and offer a necessary execution context for the various business relevant processes.

For example it might be important to know what language the user prefers to offer a response which is correctly translated. 

There are two different context classes. 

[`\Shopware\Core\Framework\Context`](https://github.com/shopware/platform/blob/master/src/Core/Framework/Context.php)
  : Supplying the Admin API and foremost the Data Abstraction Layer with necessary calculation and decision making information.
  : Containing such information as `currencyFactor` or `language`.
  
[`\Shopware\Core\System\SalesChannel\SalesChannelContext`](https://github.com/shopware/platform/blob/master/src/Core/System/SalesChannel/SalesChannelContext.php)
  : Supplying the SalesChannel-API with an additional data used from the checkout process and the catalogue for price calculation.
  : Containing such information as the current customer, the cart token or a preselected payment method.
  
The diagram below illustrates the different contexts and relations:

![context](./dist/context-relation.png) 

As you see usually the controllers are the entry point into the context distribution. Shopware 6 usually assembles a context during the kernel boot so you don't have to create your own. Each request is either marked as a sales channel or management type and the according context will be created.

### Accessing the context 

If you write your own **controller**, you can just add a type hinted `Context` or `SalesChannelContext` parameter and a `\Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface` will inject the fitting context automatically.

```php
<?php declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class IndexController extends AbstractController
{
    /**
     * @Route("/", name="example")
     */
    public function index(Context $context): Response
    {
        // ...
    }
}
```

When **no Request** is dispatched you will have to create your own context - usually from system defaults. This may either happen while writing *unit tests* or when a *command* is executed. In these cases a system default context (with a system scope) can be created manually:

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Context;

$context = Context::createDefaultContext();
``` 

*Attention: Never assemble a system context in requests, since the outcome might differ quite a lot from user expectations!*
