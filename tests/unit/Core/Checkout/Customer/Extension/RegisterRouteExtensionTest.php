<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\RegisterRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Examples\RegisterRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RegisterRouteExtension::class)]
class RegisterRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRegistration(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RegisterRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: RegisterRouteExtension::NAME,
            extension: new RegisterRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
                validateStorefrontUrl: true,
                additionalValidationDefinitions: null,
            ),
            function: static function () use (&$coreCalled): CustomerResponse {
                $coreCalled = true;

                return new CustomerResponse((new CustomerEntity())->assign(['id' => 'core']));
            },
        );

        static::assertFalse($coreCalled, 'The core registration must be skipped when a subscriber resolves it.');
        static::assertSame('example', $result->getCustomer()->getId());
    }
}
