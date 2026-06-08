<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\RegisterCustomerExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\RegisterCustomerExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(RegisterCustomerExtension::class)]
#[CoversClass(RegisterCustomerExample::class)]
class RegisterCustomerExtensionTest extends TestCase
{
    public function testSubscriberResolvesRegistration(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RegisterCustomerExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: RegisterCustomerExtension::NAME,
            extension: new RegisterCustomerExtension(
                new RequestDataBag(),
                $this->createMock(SalesChannelContext::class),
            ),
            function: static function () use (&$coreCalled): CustomerResponse {
                $coreCalled = true;

                return new CustomerResponse((new CustomerEntity())->assign(['id' => 'core']));
            },
        );

        static::assertFalse($coreCalled, 'The core registration must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(CustomerResponse::class, $result);
        static::assertSame('example', $result->getCustomer()->getId());
    }
}
