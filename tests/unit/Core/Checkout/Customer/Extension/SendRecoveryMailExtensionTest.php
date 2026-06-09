<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\SendRecoveryMailExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Tests\Examples\SendRecoveryMailExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SendRecoveryMailExtension::class)]
#[CoversClass(SendRecoveryMailExample::class)]
class SendRecoveryMailExtensionTest extends TestCase
{
    public function testSubscriberResolvesRecoveryMail(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendRecoveryMailExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: SendRecoveryMailExtension::NAME,
            extension: new SendRecoveryMailExtension(
                new RequestDataBag(),
                $this->createMock(SalesChannelContext::class),
                true,
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core recovery flow must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(SuccessResponse::class, $result);
    }
}
