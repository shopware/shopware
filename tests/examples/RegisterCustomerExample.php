<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\RegisterCustomerExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Takes over the registration — create the customer in your own way (or
 * `throw` from here to abort it) instead of running the core registration.
 */
readonly class RegisterCustomerExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RegisterCustomerExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(RegisterCustomerExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data, $event->context, $event->validateStorefrontUrl

        $customer = (new CustomerEntity())->assign(['id' => 'example']);

        $event->result = new CustomerResponse($customer);

        // stop propagation so the core registration is skipped
        $event->stopPropagation();
    }
}
