<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\ResetPasswordExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Tests\Examples\ResetPasswordExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ResetPasswordExtension::class)]
#[CoversClass(ResetPasswordExample::class)]
class ResetPasswordExtensionTest extends TestCase
{
    public function testSubscriberResolvesReset(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetPasswordExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResetPasswordExtension::NAME,
            extension: new ResetPasswordExtension(
                new RequestDataBag(),
                $this->createMock(SalesChannelContext::class),
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core reset flow must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(SuccessResponse::class, $result);
    }
}
