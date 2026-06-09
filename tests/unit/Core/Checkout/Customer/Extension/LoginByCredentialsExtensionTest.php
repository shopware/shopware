<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Extension\LoginByCredentialsExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\LoginByCredentialsExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(LoginByCredentialsExtension::class)]
#[CoversClass(LoginByCredentialsExample::class)]
class LoginByCredentialsExtensionTest extends TestCase
{
    public function testSubscriberResolvesLogin(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new LoginByCredentialsExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: LoginByCredentialsExtension::NAME,
            extension: new LoginByCredentialsExtension(
                'user@example.com',
                'secret',
                $this->createMock(SalesChannelContext::class),
            ),
            function: static function () use (&$coreCalled): string {
                $coreCalled = true;

                return 'core-token';
            },
        );

        static::assertFalse($coreCalled, 'The core login must be skipped when a subscriber resolves it.');
        static::assertSame('your-context-token', $result);
    }
}
