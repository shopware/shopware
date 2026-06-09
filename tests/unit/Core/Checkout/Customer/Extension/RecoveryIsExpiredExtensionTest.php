<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\RecoveryIsExpiredExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\RecoveryIsExpiredExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(RecoveryIsExpiredExtension::class)]
#[CoversClass(RecoveryIsExpiredExample::class)]
class RecoveryIsExpiredExtensionTest extends TestCase
{
    public function testSubscriberResolvesExpiryCheck(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RecoveryIsExpiredExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: RecoveryIsExpiredExtension::NAME,
            extension: new RecoveryIsExpiredExtension(
                new RequestDataBag(),
                $this->createMock(SalesChannelContext::class),
            ),
            function: static function () use (&$coreCalled): CustomerRecoveryIsExpiredResponse {
                $coreCalled = true;

                return new CustomerRecoveryIsExpiredResponse(true);
            },
        );

        static::assertFalse($coreCalled, 'The core expiry check must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(CustomerRecoveryIsExpiredResponse::class, $result);
    }
}
