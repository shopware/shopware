[titleEn]: <>(Creating payment methods)
[wikiUrl]: <>(../plugin-system/payment-methods?category=shopware-platform-en/plugin-system)

To create a new payment method you can simply use the repository for the payment method entity.
Add your new payment method on the installation of your plugin.
If your plugin gets deactivated or uninstalled, you should also deactivate your payment method, as it would not work without your plugin.

## Installation
```php
<?php declare(strict_types=1);

namespace SwagPayPal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Helper\PluginIdProvider;
use SwagPayPal\Core\Checkout\Payment\Cart\PaymentHandler\PayPalPayment;

class SwagPayPal extends Plugin
{
    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    private function addPaymentMethod(Context $context): void
    {
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByTechnicalName($this->getName(), $context);

        $paypal = [
            'handlerIdentifier' => PayPalPayment::class,
            'name' => 'PayPal',
            'description' => 'Bezahlung per PayPal - einfach, schnell und sicher.',
            'pluginId' => $pluginId,
        ];

        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->upsert([$paypal], $context);
    }

    ...
}
```
Prepare an array with the necessary data for the new payment method

| array key             | default value | used for |
|-----------------------|---------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| id                    | required | unique identifier for your new payment method. You should generate an own UUID and save it as constant in your plugin, so you could easily access your payment method |
| technicalName         | required | unique name for your payment method |
| name                  | `NULL` | translatable name of your payment method, which will be displayed to the customer. Have a look here, to learn more about [translations](../20-data-abstraction-layer/9-translations.md) |
| additionalDescription | `NULL` | translatable description of your payment method. Provide more information about your payment method to the customers. Have a look here, to learn more about [translations](../20-data-abstraction-layer/9-translations.md) |
| class                 | `NULL` | if you need a special handling for your payment method, you have to implement a class with the PaymentHandlerInterface. Have a look [here](../50-checkout/70-payment.md) for more information about the interface and its usage |
| pluginId              | `NULL` | to link your payment method with your plugin, you have to set the ID of your plugin here. Use the PluginIdProvider to get your ID |
| template              | `NULL` | if you need a special displaying for your payment method in the storefront, you can set a custom template here |
| position              | `1` | influence the order of the payment method |
| active                | `false` | initial state of the payment method. You should not set this to `true` as your plugin is not active on installation |

Use the payment method entity repository to create your new payment method.
If you use the `upsert()` method the payment method will be updated if it already exists.
So you could use this method also on updates of your plugin.
But note, that this will only work if you set the ID by yourself.

## De-/Activation
```php
<?php declare(strict_types=1);

namespace SwagPayPal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class SwagPayPal extends Plugin
{
    public const PAYPAL_PAYMENT_METHOD_ID = 'b8759d49b8a244ab8283f4a53f3e81fd';

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        
        // further uninstall routine
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var RepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethod = [
            'id' => self::PAYPAL_PAYMENT_METHOD_ID,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    ...
}
```
If the plugin gets activated or deactivated, you should do the same with your payment method, as it would not work without an active plugin.
You could again use the plugin entity repository to `update()` the payment method data.
Don't forget to deactivate the payment method on the deinstallation of your plugin.
