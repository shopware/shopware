<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Profiling\Command\DebugProfileCommand;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\ProfileExporter;
use Shopware\Core\Profiling\Export\SecretRedactor;
use Shopware\Core\Profiling\Export\Summarizer\CacheTagSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\DatabaseSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\ElasticsearchSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\ExceptionSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\LoggerSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\RequestSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\TimeSummarizer;
use Shopware\Core\Profiling\Export\Summarizer\TwigSummarizer;
use Shopware\Core\Profiling\FeatureFlag\FeatureFlagProfiler;
use Shopware\Core\Profiling\Routing\ProfilerWhitelist;
use Shopware\Core\Profiling\Subscriber\ActiveRulesDataCollectorSubscriber;
use Shopware\Core\Profiling\Subscriber\CacheTagCollectorSubscriber;
use Shopware\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Shopware\Core\Profiling\Twig\DoctrineExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

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

    $services->set(SecretRedactor::class);

    $services->set(CollectorDataConverter::class);

    $services->set(RequestSummarizer::class)
        ->args([
            service(CollectorDataConverter::class),
        ])
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(TimeSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(DatabaseSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(ElasticsearchSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(CacheTagSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(ExceptionSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(LoggerSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(TwigSummarizer::class)
        ->tag('shopware.profiler.panel_summarizer');

    $services->set(ProfileExporter::class)
        ->args([
            service('profiler'),
            service(CollectorDataConverter::class),
            service(SecretRedactor::class),
            tagged_iterator('shopware.profiler.panel_summarizer'),
        ]);

    $services->set(DebugProfileCommand::class)
        ->args([
            service(ProfileExporter::class),
            service(Filesystem::class),
        ])
        ->tag('console.command');
};
