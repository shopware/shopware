<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Order\LineItemDownloadLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Checkout\Order\Api\OrderActionController;
use Shopware\Core\Checkout\Order\Listener\OrderStateChangeEventListener;
use Shopware\Core\Checkout\Order\OrderAddressService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderExceptionHandler;
use Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\Subscriber\OrderSalutationSubscriber;
use Shopware\Core\Checkout\Order\Telemetry\OrderMetricsSubscriber;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OrderDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(OrderAddressDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(OrderExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(OrderCustomerDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderDeliveryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderDeliveryPositionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderLineItemDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderLineItemDownloadDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureRefundDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderTransactionCaptureRefundPositionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(OrderService::class)
        ->args([
            service(DataValidator::class),
            service(OrderValidationFactory::class),
            service('event_dispatcher'),
            service(CartService::class),
            service('payment_method.repository'),
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(OrderPersister::class)
        ->args([
            service('order.repository'),
            service(OrderConverter::class),
            service(CartSerializationCleaner::class),
        ]);

    $services->set(LineItemDownloadLoader::class)
        ->args([
            service('product_download.repository'),
        ]);

    $services->set(OrderConverter::class)
        ->args([
            service('customer.repository'),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service('order_address.repository'),
            service(InitialStateIdLoader::class),
            service(LineItemDownloadLoader::class),
            service('rule.repository'),
        ]);

    $services->set(OrderTransactionStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderTransactionCaptureStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderTransactionCaptureRefundStateHandler::class)
        ->args([
            service(StateMachineRegistry::class),
        ]);

    $services->set(OrderAddressService::class)
        ->args([
            service('order.repository'),
            service('order_address.repository'),
            service('customer_address.repository'),
            service('order_delivery.repository'),
        ]);

    // controller
    $services->set(OrderActionController::class)
        ->public()
        ->args([
            service(OrderService::class),
            service(Connection::class),
            service(PaymentRefundProcessor::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(OrderRoute::class)
        ->public()
        ->args([
            service('order.repository'),
            service('promotion.repository'),
            service('shopware.rate_limiter'),
            service('event_dispatcher'),
            service(AccountService::class),
            service(GuestAuthenticator::class),
            service(ClockInterface::class),
            param('shopware.order.deep_link.expire_days'),
        ]);

    $services->set(CancelOrderRoute::class)
        ->public()
        ->args([
            service(OrderService::class),
            service('order.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(SetPaymentOrderRoute::class)
        ->public()
        ->args([
            service(OrderService::class),
            service('order.repository'),
            service(OrderConverter::class),
            service(CartRuleLoader::class),
            service(CartService::class),
            service('event_dispatcher'),
            service(InitialStateIdLoader::class),
            service(CheckoutGatewayRoute::class),
        ]);

    // events
    $services->set(OrderStateChangeEventListener::class)
        ->args([
            service('order.repository'),
            service('order_transaction.repository'),
            service('order_delivery.repository'),
            service('event_dispatcher'),
            service(BusinessEventCollector::class),
            service('state_machine_state.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    // Telemetry: order placed metrics
    $services->set(OrderMetricsSubscriber::class)
        ->args([
            service(Meter::class),
            service(SalesChannelTypeResolver::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');
};
