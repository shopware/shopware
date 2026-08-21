<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\Command\DownloadTranslationCommand;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\Command\LintTranslationFilesCommand;
use Shopware\Core\System\Snippet\Command\ListTranslationsCommand;
use Shopware\Core\System\Snippet\Command\UpdateTranslationCommand;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SalesChannel\SalesChannelSnippetLoader;
use Shopware\Core\System\Snippet\SalesChannel\SnippetRoute;
use Shopware\Core\System\Snippet\ScheduledTask\UpdateTranslationsTask;
use Shopware\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Shopware\Core\System\Snippet\Service\AbstractTranslationConfigLoader;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\Service\TranslationFilesystemFactory;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Core\System\Snippet\SnippetFixer;
use Shopware\Core\System\Snippet\SnippetValidator;
use Shopware\Core\System\Snippet\SnippetValidatorInterface;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\System\Snippet\Subscriber\CustomFieldSubscriber;
use Shopware\Core\System\Snippet\Subscriber\LanguageDeletionSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SnippetSetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SnippetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SnippetValidatorInterface::class, SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(SnippetFixer::class)
        ->args([
            service(SnippetFileHandler::class),
        ]);

    $services->set(ValidateSnippetsCommand::class)
        ->args([
            service(SnippetValidator::class),
            service(SnippetFixer::class),
        ])
        ->tag('console.command');

    $services->set(CountryAgnosticFileLinter::class)
        ->args([
            service(Filesystem::class),
            service('plugin.repository'),
            service('app.repository'),
            inline_service(Finder::class),
        ]);

    $services->set(LintTranslationFilesCommand::class)
        ->args([
            service(CountryAgnosticFileLinter::class),
        ])
        ->tag('console.command');

    $services->set(InstallTranslationCommand::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set(DownloadTranslationCommand::class)
        ->args([
            service(AbstractTranslationLoader::class),
            service(TranslationConfig::class),
        ])
        ->tag('console.command');

    $services->set(UpdateTranslationCommand::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set(ListTranslationsCommand::class)
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set('shopware.translation.client', Client::class)
        ->args([
            [
                'timeout' => 30,
                'connect_timeout' => 5,
            ],
        ]);

    $services->set(TranslationConfigLoader::class)
        ->args([
            service('filesystem'),
            param('shopware.translation'),
        ]);

    $services->alias(AbstractTranslationConfigLoader::class, TranslationConfigLoader::class);

    $services->set(TranslationConfig::class)
        ->lazy()
        ->public()
        ->factory([service(TranslationConfigLoader::class), 'load']);

    $services->set(TranslationLoader::class)
        ->args([
            service('shopware.filesystem.translation'),
            service('language.repository'),
            service('locale.repository'),
            service('snippet_set.repository'),
            service('shopware.translation.client'),
            service(TranslationConfig::class),
            service('event_dispatcher'),
        ]);

    $services->alias(AbstractTranslationLoader::class, TranslationLoader::class);

    $services->set(TranslationMetadataStore::class)
        ->args([
            service(TranslationConfig::class),
            service('shopware.translation.client'),
            service('shopware.filesystem.translation'),
            service('cache.object'),
        ]);

    $services->set(TranslationUpdater::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ]);

    $services->set(TranslationRemover::class)
        ->args([
            service('shopware.filesystem.translation'),
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
            service('event_dispatcher'),
        ]);

    $services->set(UpdateTranslationsTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(UpdateTranslationsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(TranslationUpdater::class),
            service('language.repository'),
        ])
        ->tag('messenger.message_handler');

    $services->set(TranslationFilesystemFactory::class)
        ->args([
            service('shopware.filesystem.private'),
            service(FilesystemFactory::class),
            param('kernel.project_dir'),
            param('shopware.translation.use_local_filesystem'),
        ]);

    $services->set('shopware.filesystem.translation', FilesystemOperator::class)
        ->factory([service(TranslationFilesystemFactory::class), 'create']);

    $services->set(SalesChannelSnippetLoader::class)
        ->args([
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
            service('sales_channel.language.repository'),
        ]);

    $services->set(SnippetRoute::class)
        ->public()
        ->args([
            service(SalesChannelSnippetLoader::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SnippetFileHandler::class)
        ->args([
            service('filesystem'),
        ]);

    $services->set(CustomFieldSubscriber::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageDeletionSubscriber::class)
        ->args([
            service(Connection::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('kernel.event_subscriber');
};
