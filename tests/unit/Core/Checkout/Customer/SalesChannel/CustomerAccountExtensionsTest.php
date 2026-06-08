<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Extension\LoginByCredentialsExtension;
use Shopware\Core\Checkout\Customer\Extension\RecoveryIsExpiredExtension;
use Shopware\Core\Checkout\Customer\Extension\RegisterCustomerExtension;
use Shopware\Core\Checkout\Customer\Extension\ResetPasswordExtension;
use Shopware\Core\Checkout\Customer\Extension\SendRecoveryMailExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Tests\Examples\LoginByCredentialsExample;
use Shopware\Tests\Examples\RecoveryIsExpiredExample;
use Shopware\Tests\Examples\RegisterCustomerExample;
use Shopware\Tests\Examples\ResetPasswordExample;
use Shopware\Tests\Examples\SendRecoveryMailExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(LoginByCredentialsExtension::class)]
#[CoversClass(RegisterCustomerExtension::class)]
#[CoversClass(SendRecoveryMailExtension::class)]
#[CoversClass(ResetPasswordExtension::class)]
#[CoversClass(RecoveryIsExpiredExtension::class)]
#[CoversClass(LoginByCredentialsExample::class)]
#[CoversClass(RegisterCustomerExample::class)]
#[CoversClass(SendRecoveryMailExample::class)]
#[CoversClass(ResetPasswordExample::class)]
#[CoversClass(RecoveryIsExpiredExample::class)]
class CustomerAccountExtensionsTest extends TestCase
{
    public function testLoginByCredentialsExtension(): void
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

    public function testRegisterCustomerExtension(): void
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

    public function testSendRecoveryMailExtension(): void
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

    public function testResetPasswordExtension(): void
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

    public function testRecoveryIsExpiredExtension(): void
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
