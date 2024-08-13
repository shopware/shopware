<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Checkout\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Shopware\Core\Checkout\Gateway\Command\Event\CheckoutGatewayCommandsCollectedEvent;
use Shopware\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayload;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AppCheckoutGateway::class)]
#[Package('checkout')]
class AppCheckoutGatewayTest extends TestCase
{
    public function testProcessWithoutAppsDoesNothing(): void
    {
        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository
            ->expects(static::never())
            ->method('search');

        $gateway = new AppCheckoutGateway(
            $this->createMock(AppCheckoutGatewayPayloadService::class),
            new CheckoutGatewayCommandExecutor($this->getRegistry(), new ExceptionLogger('test', false, new NullLogger())),
            $this->createMock(CheckoutGatewayCommandRegistry::class),
            $appRepository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ExceptionLogger::class),
            $this->createMock(ActiveAppsLoader::class)
        );

        $gateway->process(new CheckoutGatewayPayloadStruct(new Cart('hatoken'), Generator::createSalesChannelContext(), new PaymentMethodCollection(), new ShippingMethodCollection()));
    }

    public function testProcess(): void
    {
        $context = Generator::createSalesChannelContext();

        $criteria = new Criteria();
        $criteria->addAssociation('paymentMethods');

        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('checkoutGatewayUrl', null),
            ]),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setCheckoutGatewayUrl('https://example.com');

        $result = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            $criteria,
            $context->getContext()
        );

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo
            ->expects(static::once())
            ->method('search')
            ->with(static::equalTo($criteria))
            ->willReturn($result);

        $id = Uuid::randomHex();

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier($id);
        $paymentMethod->setTechnicalName('payment-test');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier($id);
        $shippingMethod->setTechnicalName('shipping-test');

        $cart = new Cart('hatoken');
        $payments = new PaymentMethodCollection([$paymentMethod]);
        $shipments = new ShippingMethodCollection([$shippingMethod]);

        $payloadService = $this->createMock(AppCheckoutGatewayPayloadService::class);
        $payloadService
            ->expects(static::once())
            ->method('request')
            ->with(
                'https://example.com',
                static::equalTo(new AppCheckoutGatewayPayload($context, $cart, [$id => 'payment-test'], [$id => 'shipping-test'])),
                $app
            )
            ->willReturn(new AppCheckoutGatewayResponse([['command' => 'test', 'payload' => [['test-method']]]]));

        $registry = new CheckoutGatewayCommandRegistry([new TestCheckoutGatewayHandler()]);

        $expectedCollection = new CheckoutGatewayCommandCollection([new TestCheckoutGatewayCommand(['test-method'])]);

        $executor = new CheckoutGatewayCommandExecutor($this->getRegistry(), new ExceptionLogger('test', false, new NullLogger()));

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $payments, $shipments);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::equalTo(new CheckoutGatewayCommandsCollectedEvent($payload, $expectedCollection)));

        $loader = $this->createMock(ActiveAppsLoader::class);
        $loader->method('getActiveApps')->willReturn([$app]);

        $gateway = new AppCheckoutGateway(
            $payloadService,
            $executor,
            $registry,
            $appRepo,
            $eventDispatcher,
            $this->createMock(ExceptionLogger::class),
            $loader
        );

        $gateway->process($payload);
    }

    private function getRegistry(): CheckoutGatewayCommandRegistry
    {
        return new CheckoutGatewayCommandRegistry([new TestCheckoutGatewayHandler()]);
    }
}
