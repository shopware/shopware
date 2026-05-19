<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore - DI wiring only
 */

use Shopware\Core\Content\ImportExportV2\Controller\ImportExportV2ActionController;
use Shopware\Core\Content\ImportExportV2\File\FileService;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileDefinition;
use Shopware\Core\Content\ImportExportV2\Format\Csv\CsvExportWriter;
use Shopware\Core\Content\ImportExportV2\Format\Csv\CsvFormat;
use Shopware\Core\Content\ImportExportV2\Format\Csv\CsvImportReader;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Format\Json\JsonExportWriter;
use Shopware\Core\Content\ImportExportV2\Format\Json\JsonFormat;
use Shopware\Core\Content\ImportExportV2\Format\Json\JsonImportReader;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileDefinition;
use Shopware\Core\Content\ImportExportV2\Queue\Message\ProcessRunHandler;
use Shopware\Core\Content\ImportExportV2\Queue\Processor\ExportRunProcessor;
use Shopware\Core\Content\ImportExportV2\Queue\Processor\ImportRunProcessor;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecordBuilder;
use Shopware\Core\Content\ImportExportV2\Record\ImportPayloadBuilder;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunDefinition;
use Shopware\Core\Content\ImportExportV2\Service\ExportCriteriaEnricher;
use Shopware\Core\Content\ImportExportV2\Service\ExportFilterApplier;
use Shopware\Core\Content\ImportExportV2\Service\FailedImportRecordExporter;
use Shopware\Core\Content\ImportExportV2\Service\ImportEntityMatchResolver;
use Shopware\Core\Content\ImportExportV2\Service\ImportRecordValidator;
use Shopware\Core\Content\ImportExportV2\Service\RunService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ImportExportV2ProfileDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ImportExportV2RunDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ImportExportV2FileDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(JsonImportReader::class)
        ->args([
            new Reference(FileService::class),
        ]);

    $services->set(JsonExportWriter::class)
        ->args([
            new Reference('shopware.filesystem.private'),
        ]);

    $services->set(JsonFormat::class)
        ->args([
            new Reference(JsonImportReader::class),
            new Reference(JsonExportWriter::class),
        ])
        ->tag('shopware.import_export_v2.format');

    $services->set(CsvImportReader::class)
        ->args([
            new Reference(FileService::class),
        ]);

    $services->set(CsvExportWriter::class)
        ->args([
            new Reference('shopware.filesystem.private'),
        ]);

    $services->set(CsvFormat::class)
        ->args([
            new Reference(CsvImportReader::class),
            new Reference(CsvExportWriter::class),
        ])
        ->tag('shopware.import_export_v2.format');

    $services->set(FormatRegistry::class)
        ->args([
            new TaggedIteratorArgument('shopware.import_export_v2.format'),
        ]);

    $services->set(ImportExportRecordBuilder::class)
        ->args([
            new Reference(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ImportPayloadBuilder::class)
        ->args([
            new Reference(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ImportRecordValidator::class);

    $services->set(ImportEntityMatchResolver::class)
        ->args([
            new Reference(DefinitionInstanceRegistry::class),
        ]);

    $services->set(FailedImportRecordExporter::class)
        ->args([
            new Reference(FormatRegistry::class),
            new Reference(FileService::class),
        ]);

    $services->set(ExportCriteriaEnricher::class)
        ->args([
            new Reference(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ExportFilterApplier::class)
        ->args([
            new Reference(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ImportRunProcessor::class)
        ->args([
            new Reference(FormatRegistry::class),
            new Reference(ImportPayloadBuilder::class),
            new Reference(DefinitionInstanceRegistry::class),
            new Reference(ImportRecordValidator::class),
            new Reference(ImportEntityMatchResolver::class),
            new Reference(FailedImportRecordExporter::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services->set(ExportRunProcessor::class)
        ->args([
            new Reference(FormatRegistry::class),
            new Reference(ExportCriteriaEnricher::class),
            new Reference(ImportExportRecordBuilder::class),
            new Reference(DefinitionInstanceRegistry::class),
            new Reference(ExportFilterApplier::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services->set(FileService::class)
        ->args([
            new Reference('shopware.filesystem.private'),
            new Reference('import_export_v2_file.repository'),
        ]);

    $services->set(ImportExportV2ActionController::class)
        ->public()
        ->tag('controller.service_arguments')
        ->args([
            new Reference(RunService::class),
            new Reference(FileService::class),
            new Reference('import_export_v2_profile.repository'),
        ]);

    $services->set(RunService::class)
        ->public()
        ->args([
            new Reference('import_export_v2_profile.repository'),
            new Reference(FormatRegistry::class),
            new Reference(ImportRunProcessor::class),
            new Reference(ExportRunProcessor::class),
            new Reference('import_export_v2_run.repository'),
            new Reference(FileService::class),
            new Reference('messenger.default_bus'),
            new Reference(\Doctrine\DBAL\Connection::class),
        ]);

    $services->set(ProcessRunHandler::class)
        ->args([
            new Reference(RunService::class),
        ])
        ->tag('messenger.message_handler');
};
