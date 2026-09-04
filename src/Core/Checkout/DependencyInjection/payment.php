<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Payment\Api\PaymentMethodTechnicalNameFkResolver;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentMethodValidator as CartPaymentMethodValidator;
use Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegisteredValidator;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTask;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTaskHandler;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodDataLoader;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodLoaderConfigSerializer;
use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameGenerator;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodValidator;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\SalesChannelPaymentMethodDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PaymentMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelPaymentMethodDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(PaymentMethodTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PaymentMethodValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PaymentProcessor::class)
        ->args([
            service(JWTFactoryV2::class)->nullOnInvalid(),
            service(PaymentTokenGenerator::class),
            service(PaymentTokenLifecycle::class),
            service(PaymentHandlerRegistry::class),
            service('order_transaction.repository'),
            service(OrderTransactionStateHandler::class),
            service('logger'),
            service(PaymentTransactionStructFactory::class),
            service(InitialStateIdLoader::class),
            service('router'),
            service(SystemConfigService::class),
        ]);

    $services->set(PaymentController::class)
        ->public()
        ->args([
            service(PaymentProcessor::class),
            service(OrderConverter::class),
            service(JWTFactoryV2::class)->nullOnInvalid(),
            service(PaymentTokenGenerator::class),
            service(PaymentTokenLifecycle::class),
            service('order.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(PaymentTransactionStructFactory::class);

    $services->set(PaymentRefundProcessor::class)
        ->public()
        ->args([
            service(Connection::class),
            service(OrderTransactionCaptureRefundStateHandler::class),
            service(PaymentHandlerRegistry::class),
            service(PaymentTransactionStructFactory::class),
        ]);

    $services->set(PaymentRecurringProcessor::class)
        ->public()
        ->args([
            service('order_transaction.repository'),
            service(InitialStateIdLoader::class),
            service(OrderTransactionStateHandler::class),
            service(PaymentHandlerRegistry::class),
            service(PaymentTransactionStructFactory::class),
            service('logger'),
        ]);

    $services->set(JWTFactoryV2::class)
        ->args([
            service('shopware.jwt_config'),
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(PaymentTokenRegisteredValidator::class)
        ->args([
            service(PaymentTokenLifecycle::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(PaymentTokenGenerator::class)
        ->args([
            service('shopware.jwt_config'),
            service(DataValidator::class),
            service(SystemConfigService::class),
        ]);

    $services->set(PaymentTokenLifecycle::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PaymentHandlerRegistry::class)
        ->args([
            tagged_locator('shopware.payment.method'),
            service(Connection::class),
        ]);

    $services->set(PrePayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(DebitPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(CashPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(InvoicePayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(DefaultPayment::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(PaymentHandlerIdentifierSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PaymentDistinguishableNameSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PaymentMethodIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('event_dispatcher'),
            service('payment_method.repository'),
            service(PaymentDistinguishableNameGenerator::class),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(PaymentDistinguishableNameGenerator::class)
        ->args([
            service('payment_method.repository'),
        ]);

    $services->set(CartPaymentMethodValidator::class)
        ->tag('shopware.cart.validator');

    $services->set(CleanupPaymentTokenTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupPaymentTokenTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    // Sales Channel API
    $services->set(PaymentMethodRoute::class)
        ->public()
        ->args([
            service('sales_channel.payment_method.repository'),
            service(CacheTagCollector::class),
            service(ScriptExecutor::class),
            service(RuleIdMatcher::class),
        ]);

    $services->set(HandlePaymentMethodRoute::class)
        ->public()
        ->args([
            service(PaymentProcessor::class),
            service(DataValidator::class),
            service(SalesChannelContextService::class),
            service('currency.repository'),
        ]);

    $services->set(PaymentMethodTechnicalNameFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');

    // Content System
    $services->alias(AbstractPaymentMethodRoute::class, PaymentMethodRoute::class);

    $services->set(PaymentMethodDataLoader::class)
        ->args([
            service(AbstractPaymentMethodRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(PaymentMethodLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};
