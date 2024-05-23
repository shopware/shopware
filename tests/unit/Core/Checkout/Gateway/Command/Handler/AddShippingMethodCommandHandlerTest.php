<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AddShippingMethodCommand;
use Shopware\Core\Checkout\Gateway\Command\Handler\AddShippingMethodCommandHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AddShippingMethodCommandHandler::class)]
#[Package('checkout')]
class AddShippingMethodCommandHandlerTest extends TestCase
{
    public function testSupportsCommands(): void
    {
        static::assertSame(
            [AddShippingMethodCommand::class],
            AddShippingMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandler(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier(Uuid::randomHex());
        $shippingMethod->setTechnicalName('test');

        $result = new EntitySearchResult(
            ShippingMethodDefinition::ENTITY_NAME,
            1,
            new ShippingMethodCollection([$shippingMethod]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(
                    static function (Criteria $criteria): bool {
                        static::assertCount(1, $criteria->getFilters());

                        /** @var EqualsFilter $filter */
                        $filter = $criteria->getFilters()[0];

                        static::assertInstanceOf(EqualsFilter::class, $filter);
                        static::assertSame('technicalName', $filter->getField());
                        static::assertSame('test', $filter->getValue());

                        static::assertTrue($criteria->hasAssociation('appShippingMethod'));
                        $assoc = $criteria->getAssociation('appShippingMethod');
                        static::assertTrue($assoc->hasAssociation('app'));

                        return true;
                    }
                ),
                static::isInstanceOf(Context::class)
            )
            ->willReturn($result);

        $command = new AddShippingMethodCommand('test');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = Generator::createSalesChannelContext();

        $handler = new AddShippingMethodCommandHandler($repo, $this->createMock(ExceptionLogger::class));
        $handler->handle($command, $response, $context);

        static::assertSame($shippingMethod, $response->getAvailableShippingMethods()->first());
    }

    public function testShippingMethodNotFoundThrows(): void
    {
        $result = new EntitySearchResult(
            ShippingMethodDefinition::ENTITY_NAME,
            0,
            new ShippingMethodCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $command = new AddShippingMethodCommand('test');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = Generator::createSalesChannelContext();

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects(static::once())
            ->method('logOrThrowException')
            ->with(static::equalTo(CheckoutGatewayException::handlerException('Shipping method "{{ technicalName }}" not found', ['technicalName' => 'test'])));

        $handler = new AddShippingMethodCommandHandler($repo, $logger);
        $handler->handle($command, $response, $context);
    }
}
