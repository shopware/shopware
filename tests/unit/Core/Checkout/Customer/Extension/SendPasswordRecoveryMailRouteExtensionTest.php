<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\SendPasswordRecoveryMailRouteExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Examples\SendPasswordRecoveryMailRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SendPasswordRecoveryMailRouteExtension::class)]
class SendPasswordRecoveryMailRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesRecoveryMail(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendPasswordRecoveryMailRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: SendPasswordRecoveryMailRouteExtension::NAME,
            extension: new SendPasswordRecoveryMailRouteExtension(
                new RequestDataBag(),
                Generator::generateSalesChannelContext(),
                validateStorefrontUrl: true,
            ),
            function: static function () use (&$coreCalled): SuccessResponse {
                $coreCalled = true;

                return new SuccessResponse();
            },
        );

        static::assertFalse($coreCalled, 'The core recovery flow must be skipped when a subscriber resolves it.');
    }
}
