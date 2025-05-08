<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeCheckoutOptionsCommandHandler;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeCheckoutOptionsCommandHandler::class)]
class ChangeCheckoutOptionsCommandHandlerTest extends TestCase
{
    public function testHandleShippingMethodCommand(): void
    {
        $command = ChangeShippingMethodCommand::createFromPayload(['technicalName' => 'test_app_shipping']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('technicalName', 'test_app_shipping'));

        $shippingMethodResult = new IdSearchResult(
            1,
            [['primaryKey' => 'shippingMethodId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $shippingMethodRepo = $this->createMock(EntityRepository::class);
        $shippingMethodRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($shippingMethodResult);

        $handler = new ChangeCheckoutOptionsCommandHandler($this->createMock(EntityRepository::class), $shippingMethodRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['shippingMethodId' => 'shippingMethodId'], $parameters);
    }

    public function testHandleShippingMethodCommandWithShippingMethodNotFound(): void
    {
        $command = ChangeShippingMethodCommand::createFromPayload(['technicalName' => 'test_app_shipping']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('technicalName', 'test_app_shipping'));

        $shippingMethodResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $shippingMethodRepo = $this->createMock(EntityRepository::class);
        $shippingMethodRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($shippingMethodResult);

        $this->expectExceptionObject(GatewayException::handlerException('Shipping method with technical name {{ technicalName }} not found', ['technicalName' => 'test_app_shipping']));

        $handler = new ChangeCheckoutOptionsCommandHandler($this->createMock(EntityRepository::class), $shippingMethodRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame([], $parameters);
    }

    public function testHandlePaymentMethodCommand(): void
    {
        $command = ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'test_app_payment']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('technicalName', 'test_app_payment'));

        $paymentMethodResult = new IdSearchResult(
            1,
            [['primaryKey' => 'paymentMethodId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $paymentMethodRepo = $this->createMock(EntityRepository::class);
        $paymentMethodRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($paymentMethodResult);

        $handler = new ChangeCheckoutOptionsCommandHandler($paymentMethodRepo, $this->createMock(EntityRepository::class));
        $handler->handle($command, $context, $parameters);

        static::assertSame(['paymentMethodId' => 'paymentMethodId'], $parameters);
    }

    public function testHandlePaymentMethodCommandWithPaymentMethodNotFound(): void
    {
        $command = ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'test_app_payment']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('technicalName', 'test_app_payment'));

        $paymentMethodResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $paymentMethodRepo = $this->createMock(EntityRepository::class);
        $paymentMethodRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($paymentMethodResult);

        $this->expectExceptionObject(GatewayException::handlerException('Payment method with technical name {{ technicalName }} not found', ['technicalName' => 'test_app_payment']));

        $handler = new ChangeCheckoutOptionsCommandHandler($paymentMethodRepo, $this->createMock(EntityRepository::class));
        $handler->handle($command, $context, $parameters);

        static::assertSame([], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([
            ChangeShippingMethodCommand::class,
            ChangePaymentMethodCommand::class,
        ], ChangeCheckoutOptionsCommandHandler::supportedCommands());
    }
}
