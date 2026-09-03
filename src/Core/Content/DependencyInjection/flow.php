<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\RuleLoader;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator as DocumentV2Generator;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Controller\TriggerFlowController;
use Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerAffiliateAndCampaignCodeAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderAffiliateAndCampaignCodeAction;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\ChangeCustomerGroupAction;
use Shopware\Core\Content\Flow\Dispatching\Action\ChangeCustomerStatusAction;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveCustomerTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SetCustomerCustomFieldAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SetCustomerGroupCustomFieldAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderCustomFieldAction;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutionTriggersListener;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Shopware\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomAppStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerRecoveryStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\LanguageStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\MessageStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ProductStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\SalesChannelContextStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\Indexing\FlowBuilder;
use Shopware\Core\Content\Flow\Indexing\FlowIndexer;
use Shopware\Core\Content\Flow\Indexing\FlowIndexerSubscriber;
use Shopware\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Shopware\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Shopware\Core\Content\Flow\Rule\OrderCreatedByAdminRule;
use Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule;
use Shopware\Core\Content\Flow\Rule\OrderDeliveryStatusRule;
use Shopware\Core\Content\Flow\Rule\OrderDocumentTypeRule;
use Shopware\Core\Content\Flow\Rule\OrderDocumentTypeSentRule;
use Shopware\Core\Content\Flow\Rule\OrderStatusRule;
use Shopware\Core\Content\Flow\Rule\OrderTagRule;
use Shopware\Core\Content\Flow\Rule\OrderTrackingCodeRule;
use Shopware\Core\Content\Flow\Rule\OrderTransactionStatusRule;
use Shopware\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Shopware\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Shopware\Core\Content\Shared\MailFlow\DocumentResolver;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FlowDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FlowSequenceDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FlowDispatcher::class)
        ->decorate('event_dispatcher', null, 1000)
        ->args([
            service(FlowDispatcher::class . '.inner'),
            service_locator([
                'logger' => service('logger'),
                Connection::class => service(Connection::class),
                FlowFactory::class => service(FlowFactory::class),
                FlowExecutor::class => service(FlowExecutor::class),
                FlowLoader::class => service(FlowLoader::class),
                BufferedFlowQueue::class => service(BufferedFlowQueue::class),
            ]),
        ]);

    $services->set(BufferedFlowQueue::class);

    $services->set(BufferedFlowExecutor::class)
        ->public()
        ->args([
            service(BufferedFlowQueue::class),
            service(FlowLoader::class),
            service(FlowFactory::class),
            service(FlowExecutor::class),
            service('logger'),
        ]);

    $services->set(BufferedFlowExecutionTriggersListener::class)
        ->args([
            service_locator([
                BufferedFlowExecutor::class => service(BufferedFlowExecutor::class),
            ]),
            service(BufferedFlowQueue::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(FlowRuleScopeBuilder::class)
        ->args([
            service(OrderConverter::class),
            service(DeliveryBuilder::class),
            tagged_iterator('shopware.cart.collector'),
        ]);

    $services->set(FlowExecutor::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(AppFlowActionProvider::class),
            service(RuleLoader::class),
            service(FlowRuleScopeBuilder::class),
            service(Connection::class),
            service(ExtensionDispatcher::class),
            service('logger'),
            tagged_iterator('flow.action', 'key'),
            service(FlowMetricsInstrumentor::class),
        ]);

    $services->set(TriggerGroupResolver::class);

    $services->set(FlowMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(TriggerGroupResolver::class),
        ]);

    $services->set(AddOrderTagAction::class)
        ->args([
            service('order.repository'),
        ])
        ->tag('flow.action', ['priority' => 1000, 'key' => 'action.add.order.tag']);

    $services->set(AddCustomerTagAction::class)
        ->args([
            service('customer.repository'),
        ])
        ->tag('flow.action', ['priority' => 900, 'key' => 'action.add.customer.tag']);

    $services->set(RemoveOrderTagAction::class)
        ->args([
            service('order_tag.repository'),
        ])
        ->tag('flow.action', ['priority' => 800, 'key' => 'action.remove.order.tag']);

    $services->set(RemoveCustomerTagAction::class)
        ->args([
            service('customer_tag.repository'),
        ])
        ->tag('flow.action', ['priority' => 700, 'key' => 'action.remove.customer.tag']);

    $services->set(ChangeCustomerGroupAction::class)
        ->args([
            service('customer.repository'),
        ])
        ->tag('flow.action', ['priority' => 690, 'key' => 'action.change.customer.group']);

    $services->set(ChangeCustomerStatusAction::class)
        ->args([
            service('customer.repository'),
        ])
        ->tag('flow.action', ['priority' => 680, 'key' => 'action.change.customer.status']);

    $services->set(GrantDownloadAccessAction::class)
        ->args([
            service('order_line_item_download.repository'),
        ])
        ->tag('flow.action', ['priority' => 550, 'key' => 'action.grant.download.access']);

    $services->set(SendMailAction::class)
        ->args([
            service(MailService::class),
            service('mail_template.repository'),
            service('logger'),
            service('event_dispatcher'),
            service('mail_template_type.repository'),
            service(Translator::class),
            service(Connection::class),
            service(LanguageLocaleCodeProvider::class),
            service(JsonEntityEncoder::class),
            service(DefinitionInstanceRegistry::class),
            param('shopware.mail.update_mail_variables_on_send'),
        ])
        ->tag('flow.action', ['priority' => 500, 'key' => 'action.mail.send']);

    $services->set(GenerateDocumentAction::class)
        ->args([
            service(DocumentGenerator::class),
            service(DocumentV2Generator::class),
            service('logger'),
        ])
        ->tag('flow.action', ['priority' => 620, 'key' => 'action.generate.document']);

    $services->set(SetOrderStateAction::class)
        ->args([
            service(Connection::class),
            service(OrderService::class),
        ])
        ->tag('flow.action', ['priority' => 400, 'key' => 'action.set.order.state']);

    $services->set(SetCustomerCustomFieldAction::class)
        ->args([
            service(Connection::class),
            service('customer.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.set.customer.custom.field']);

    $services->set(SetOrderCustomFieldAction::class)
        ->args([
            service(Connection::class),
            service('order.repository'),
        ])
        ->tag('flow.action', ['priority' => 300, 'key' => 'action.set.order.custom.field']);

    $services->set(SetCustomerGroupCustomFieldAction::class)
        ->args([
            service(Connection::class),
            service('customer_group.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.set.customer.group.custom.field']);

    $services->set(AddCustomerAffiliateAndCampaignCodeAction::class)
        ->args([
            service(Connection::class),
            service('customer.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.add.customer.affiliate.and.campaign.code']);

    $services->set(AddOrderAffiliateAndCampaignCodeAction::class)
        ->args([
            service(Connection::class),
            service('order.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.add.order.affiliate.and.campaign.code']);

    $services->set(StopFlowAction::class)
        ->tag('flow.action', ['priority' => 1, 'key' => 'action.stop.flow']);

    $services->set(FlowActionCollector::class)
        ->args([
            tagged_iterator('flow.action'),
            service('event_dispatcher'),
            service('app_flow_action.repository'),
        ]);

    $services->set(FlowLoader::class)
        ->public()
        ->args([
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(CachedFlowLoader::class)
        ->decorate(FlowLoader::class, null, -1000)
        ->public()
        ->args([
            service(CachedFlowLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(FlowPayloadUpdater::class)
        ->args([
            service(Connection::class),
            service(FlowBuilder::class),
            service(CacheInvalidator::class),
        ]);

    $services->set(FlowIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('flow.repository'),
            service(FlowPayloadUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(FlowIndexerSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(FlowBuilder::class);

    $services->set(SalesChannelContextStorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
        ])
        ->tag('flow.storer');

    $services->set(OrderStorer::class)
        ->args([
            service('order.repository'),
            service('event_dispatcher'),
            service(OrderProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(ProductStorer::class)
        ->args([
            service('product.repository'),
            service('event_dispatcher'),
            service(ProductProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(A11yRenderedDocumentStorer::class)
        ->args([
            service('document.repository'),
            service('event_dispatcher'),
            service(DocumentResolver::class),
            service(DocumentFileResolver::class),
        ])
        ->tag('flow.storer');

    $services->set(CustomerStorer::class)
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(CustomerProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(MailStorer::class)
        ->tag('flow.storer');

    $services->set(UserStorer::class)
        ->args([
            service('user_recovery.repository'),
            service('event_dispatcher'),
            service(UserRecoveryProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(CustomerGroupStorer::class)
        ->args([
            service('customer_group.repository'),
            service('event_dispatcher'),
            service(CustomerGroupProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(CustomerRecoveryStorer::class)
        ->args([
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(CustomerRecoveryProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(OrderTransactionStorer::class)
        ->args([
            service('order_transaction.repository'),
            service('event_dispatcher'),
            service(OrderTransactionProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(NewsletterRecipientStorer::class)
        ->args([
            service('newsletter_recipient.repository'),
            service('event_dispatcher'),
            service(NewsletterRecipientProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(ScalarValuesStorer::class)
        ->tag('flow.storer');

    $services->set(MessageStorer::class)
        ->tag('flow.storer');

    $services->set(CustomAppStorer::class)
        ->tag('flow.storer', ['priority' => 999]);

    $services->set(LanguageStorer::class)
        ->tag('flow.storer');

    $services->set(TimezoneStorer::class)
        ->args([
            service(RequestStack::class),
        ])
        ->tag('flow.storer');

    $services->set(FlowFactory::class)
        ->public()
        ->args([
            tagged_iterator('flow.storer'),
        ]);

    $services->set(OrderTagRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderTrackingCodeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderDeliveryStatusRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderCreatedByAdminRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderTransactionStatusRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderStatusRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderCustomFieldRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderDocumentTypeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrderDocumentTypeSentRule::class)
        ->tag('shopware.rule.definition');

    $services->set(FlowTemplateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FlowTemplateConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(TriggerFlowController::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service('app_flow_event.repository'),
            service(DataValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
