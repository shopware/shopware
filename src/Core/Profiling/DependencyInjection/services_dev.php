<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityAggregator;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopware\Core\Profiling\FeatureFlag\FeatureFlagProfiler;
use Shopware\Core\Profiling\Routing\ProfilerWhitelist;
use Shopware\Core\Profiling\Subscriber\ActiveRulesDataCollectorSubscriber;
use Shopware\Core\Profiling\Subscriber\CacheTagCollectorSubscriber;
use Shopware\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Shopware\Core\Profiling\Twig\DoctrineExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProfilerController::class)
        ->args([
            service('twig'),
            service('profiler'),
            service(Connection::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(ProfilerWhitelist::class)
        ->tag('shopware.route_scope_whitelist');

    $services->set(ConnectionProfiler::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('data_collector', [
            'template' => '@Profiling/Collector/db.html.twig',
            'id' => 'app.connection_collector',
            'priority' => 200,
        ]);

    $services->set(DalCollector::class)
        ->args([
            service(Connection::class),
            tagged_iterator('shopware.profiler.dal_request_log'),
        ])
        ->tag('data_collector', [
            'template' => '@Profiling/Collector/dal.html.twig',
            'id' => 'dal',
            'priority' => 199,
        ]);

    $services->set(ProfilingEntityReader::class)
        ->decorate(EntityReaderInterface::class, null, \PHP_INT_MIN)
        ->args([
            service(ProfilingEntityReader::class . '.inner'),
            service(DalCollector::class),
        ]);

    $services->set(ProfilingEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class, null, \PHP_INT_MIN)
        ->args([
            service(ProfilingEntitySearcher::class . '.inner'),
            service(DalCollector::class),
        ]);

    $services->set(ProfilingEntityAggregator::class)
        ->decorate(EntityAggregatorInterface::class, null, \PHP_INT_MIN)
        ->args([
            service(ProfilingEntityAggregator::class . '.inner'),
            service(DalCollector::class),
        ]);

    $services->set(ProfilingEntityWriter::class)
        ->decorate(EntityWriter::class, null, \PHP_INT_MIN)
        ->args([
            service(ProfilingEntityWriter::class . '.inner'),
            service(DalCollector::class),
        ]);

    $services->set(DoctrineExtension::class)
        ->tag('twig.extension');

    $services->set(ActiveRulesDataCollectorSubscriber::class)
        ->args([
            service('rule.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');

    $services->set(FeatureFlagProfiler::class)
        ->args([
            service(FeatureFlagRegistry::class),
        ])
        ->tag('data_collector', [
            'template' => '@Profiling/Collector/flags.html.twig',
            'id' => 'feature_flag',
            'priority' => -5,
        ]);

    $services->set(CacheTagCollectorSubscriber::class)
        ->args([
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');

    $services->set(CartDataCollectorSubscriber::class)
        ->args([
            service(CartPersister::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('data_collector');
};
