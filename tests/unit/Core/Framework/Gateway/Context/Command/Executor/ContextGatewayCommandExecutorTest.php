<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\TestAllCommandsGatewayCommandHandler;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\TestContextGatewayCommand;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayCommandExecutor::class)]
class ContextGatewayCommandExecutorTest extends TestCase
{
    public function testExecuteWithRegisterCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(RegisterCustomerCommand::createFromPayload(['data' => ['firstName' => 'Foo', 'lastName' => 'bar']]));

        $context = Generator::generateSalesChannelContext();
        $newContext = Generator::generateSalesChannelContext(token: 'hatoken');

        $registry = new ContextGatewayCommandRegistry([new TestAllCommandsGatewayCommandHandler()]);
        $salesChannelService = $this->createMock(SalesChannelContextServiceInterface::class);
        $salesChannelService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($context->getSalesChannelId(), 'hatoken')))
            ->willReturn($newContext);

        $executor = new ContextGatewayCommandExecutor(
            $this->createMock(AbstractContextSwitchRoute::class),
            $registry,
            $this->createMock(ContextGatewayCommandValidator::class),
            $this->createMock(ExceptionLogger::class),
            $salesChannelService
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testExecuteWithLoginCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(LoginCustomerCommand::createFromPayload(['customerEmail' => 'hatoken']));

        $context = Generator::generateSalesChannelContext();
        $newContext = Generator::generateSalesChannelContext(token: 'hatoken');

        $registry = new ContextGatewayCommandRegistry([new TestAllCommandsGatewayCommandHandler()]);
        $salesChannelService = $this->createMock(SalesChannelContextServiceInterface::class);
        $salesChannelService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($context->getSalesChannelId(), 'hatoken')))
            ->willReturn($newContext);

        $executor = new ContextGatewayCommandExecutor(
            $this->createMock(AbstractContextSwitchRoute::class),
            $registry,
            $this->createMock(ContextGatewayCommandValidator::class),
            $this->createMock(ExceptionLogger::class),
            $salesChannelService
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testExecuteWithDifferentCommands(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(ChangeBillingAddressCommand::createFromPayload(['addressId' => 'billingAddressId']));
        $commands->add(ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']));
        $commands->add(ChangeLanguageCommand::createFromPayload(['iso' => 'de-DE']));
        $commands->add(ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'app_test_payment']));
        $commands->add(ChangeShippingAddressCommand::createFromPayload(['addressId' => 'shippingAddressId']));
        $commands->add(ChangeShippingLocationCommand::createFromPayload(['countryIso' => 'DE', 'countryStateIso' => 'DE-BY']));
        $commands->add(ChangeShippingMethodCommand::createFromPayload(['technicalName' => 'app_test_shipping']));

        $context = Generator::generateSalesChannelContext();
        $expectedContextParameters = new RequestDataBag([
            'billingAddress' => 'billingAddressId',
            'currencyId' => 'EUR',
            'languageId' => 'de-DE',
            'paymentMethod' => 'app_test_payment',
            'shippingAddress' => 'shippingAddressId',
            'countryId' => 'DE',
            'countryStateId' => 'DE-BY',
            'shippingMethod' => 'app_test_shipping',
        ]);

        $registry = new ContextGatewayCommandRegistry([new TestAllCommandsGatewayCommandHandler()]);

        $switchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $switchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with($expectedContextParameters, $context)
            ->willReturn(new ContextTokenResponse('hatoken'));

        $executor = new ContextGatewayCommandExecutor(
            $switchRoute,
            $registry,
            $this->createMock(ContextGatewayCommandValidator::class),
            $this->createMock(ExceptionLogger::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
        );

        $response = $executor->execute($commands, $context);

        static::assertSame('hatoken', $response->getToken());
    }

    public function testExecuteWithUnknownCommand(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(TestContextGatewayCommand::createFromPayload());

        $context = Generator::generateSalesChannelContext();

        $registry = new ContextGatewayCommandRegistry([]);

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects($this->once())
            ->method('logOrThrowException')
            ->with(GatewayException::handlerNotFound(TestContextGatewayCommand::getDefaultKeyName()));

        $executor = new ContextGatewayCommandExecutor(
            $this->createMock(AbstractContextSwitchRoute::class),
            $registry,
            $this->createMock(ContextGatewayCommandValidator::class),
            $logger,
            $this->createMock(SalesChannelContextServiceInterface::class),
        );

        $response = $executor->execute($commands, $context);

        static::assertSame($context->getToken(), $response->getToken());
    }
}
