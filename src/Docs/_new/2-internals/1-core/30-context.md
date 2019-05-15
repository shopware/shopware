[titleEn]: <>(Context object)

The platform processes some user-, application- or environment specific information.
For example it might be important to know what language the user prefer to offer a
response which is correctly translated. In order to allow developers to work with this data,
different contexts are created during the boot process. Here is a list of context objects
and their properties:

* `Shopware\Core\Framework\SourceContext`
    * origin (api, storefront, system)
    * userId (optional)
    * integrationId (optional)
    * salesChannelId (optional)
* `Shopware\Core\Framework\Context` most common context, includes the `SourceContext`
    * languageId
    * fallbackLanguageId
    * versionId
    * sourceContext (see above)
    * catalogIds (optional)
    * currencyId
    * currencyFactor
    * rules
    * writeProtection

For the sake of completeness, there is an even more comprehensive context called `CheckoutContext`
which is not part of the getting started guide.

The platform usually assembles a context during the kernel boot so you don't have to create your own.
If you need a generic context for writing unit tests you can use:

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Context;

$context = Context::createDefaultContext();
``` 

Attention: Never assemble a generic context for production!

### Using the context inside a controller

If you write your own controller, you can just add the `Context` parameter
and the `Shopware\Core\Framework\Api\Context\ContextValueResolver` will
inject the right Context automatically.

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
