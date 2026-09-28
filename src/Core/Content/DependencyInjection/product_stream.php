<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamFilterChangeSetSubscriber;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\ProductStream\ScheduledTask\UpdateProductStreamMappingTask;
use Shopware\Core\Content\ProductStream\ScheduledTask\UpdateProductStreamMappingTaskHandler;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProductStreamDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductStreamTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductStreamFilterDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductStreamBuilder::class)
        ->public()
        ->args([
            service('product_stream.repository'),
            service(ProductDefinition::class),
        ]);

    $services->alias(ProductStreamBuilderInterface::class, ProductStreamBuilder::class)
        ->deprecate('shopware/core', '6.8.0', 'The %alias_id% service is deprecated and will be removed in 6.8.0. Use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder instead');

    $services->set(ProductStreamIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_stream.repository'),
            service('serializer'),
            service(ProductDefinition::class),
            service('event_dispatcher'),
        ])
        // Must run before ProductIndexer so it compiles stream filters before ProductStreamUpdater creates mappings.
        ->tag('shopware.entity_indexer', ['priority' => 110]);

    $services->set(ProductStreamFilterChangeSetSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(UpdateProductStreamMappingTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(UpdateProductStreamMappingTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('product_stream.repository'),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');
};
