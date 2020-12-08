<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\ApiVersion\ApiVersionSubscriber;
use Shopware\Core\Framework\Api\Converter\ApiConverter;
use Shopware\Core\Framework\Api\Converter\ConverterRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ApiVersioningTestBehaviour
{
    protected static function container(): ContainerInterface
    {
        return KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
    }

    protected static function clearCache(): void
    {
        $cacheId = Uuid::randomHex();
        static::container()->get(Connection::class)->executeUpdate('
            UPDATE `app_config`
            SET `value` = :cacheId
            WHERE `key` = "cache-id"
        ', ['cacheId' => $cacheId]);

        KernelLifecycleManager::getKernel()->reboot(null, null, $cacheId);
    }

    /**
     * @param ApiConverter[] $apiVersions
     * @psalm-suppress InvalidScope
     */
    protected static function setApiVersions(array $apiVersions): void
    {
        $converterRegistry = static::container()->get(ConverterRegistry::class);
        $closure = \Closure::fromCallable(function () use ($apiVersions): void {
            $this->converters = $apiVersions;
        });
        $closure = \Closure::bind($closure, $converterRegistry, $converterRegistry);
        $closure();

        $apiVersionSubscriber = static::container()->get(ApiVersionSubscriber::class);
        $closure = \Closure::fromCallable(function () use ($apiVersions): void {
            $this->supportedApiVersions = array_keys($apiVersions);
        });
        $closure = \Closure::bind($closure, $apiVersionSubscriber, $apiVersionSubscriber);
        $closure();
    }

    /**
     * @psalm-suppress InvalidScope
     */
    protected static function registerDefinition(string ...$definitionClasses): void
    {
        $definitionRegistry = static::container()->get(DefinitionInstanceRegistry::class);
        $definitions = [];
        $repositories = [];

        foreach ($definitionClasses as $definitionClass) {
            /** @var EntityDefinition $definition */
            $definition = new $definitionClass();
            $definition->compile($definitionRegistry);
            static::container()->set($definitionClass, $definition);
            $definitions[$definition->getEntityName()] = $definitionClass;

            static::container()->set($definition->getEntityName() . '.repository', new EntityRepository(
                $definition,
                static::container()->get(EntityReaderInterface::class),
                static::container()->get(VersionManager::class),
                static::container()->get(EntitySearcherInterface::class),
                static::container()->get(EntityAggregatorInterface::class),
                static::container()->get('event_dispatcher')
            ));
            $repositories[$definition->getEntityName()] = $definition->getEntityName() . '.repository';
        }

        $closure = \Closure::fromCallable(function () use ($definitions, $repositories): void {
            $this->definitions = array_merge($definitions, $this->definitions);
            $this->repositoryMap = array_merge($repositories, $this->repositoryMap);
        });
        $closure = \Closure::bind($closure, $definitionRegistry, $definitionRegistry);
        $closure();
    }

    /**
     * @param MigrationStep[] $destructiveMigrations
     * @param MigrationStep[] $migrations
     */
    protected static function runMigrations(array $destructiveMigrations, array $migrations): void
    {
        $connection = static::container()->get(Connection::class);

        foreach ($destructiveMigrations as $destructiveMigration) {
            $destructiveMigration->update($connection);
            $destructiveMigration->updateDestructive($connection);
        }

        foreach ($migrations as $migration) {
            $migration->update($connection);
        }
    }
}
