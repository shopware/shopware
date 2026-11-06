<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\CustomerRecoveryIsExpiredRouteExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Examples\CustomerRecoveryIsExpiredRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRecoveryIsExpiredRouteExtension::class)]
class CustomerRecoveryIsExpiredRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesExpiryCheck(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new CustomerRecoveryIsExpiredRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: CustomerRecoveryIsExpiredRouteExtension::NAME,
            extension: new CustomerRecoveryIsExpiredRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
            ),
            function: static function () use (&$coreCalled): CustomerRecoveryIsExpiredResponse {
                $coreCalled = true;

                return new CustomerRecoveryIsExpiredResponse(true);
            },
        );

        static::assertFalse($coreCalled, 'The core expiry check must be skipped when a subscriber resolves it.');
        static::assertFalse($result->isExpired());
    }
}
