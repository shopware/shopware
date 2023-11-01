<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
#[Package('core')]
class EntityGenerator implements ScaffoldingGenerator
{
    use HasCommandOption;

    public const OPTION_NAME = 'entities';
    private const OPTION_DESCRIPTION = 'list of entities to generate (PascalCase, comma separated)';

    private string $servicesXmlEntry = <<<'EOL'

            <service id="{{ namespace }}\Core\Content\{{ entityName }}\{{ entityName }}Definition">
                <tag name="shopware.entity.definition" entity="{{ tableName }}" />
            </service>

    EOL;

    public function __construct(private readonly \DateTimeImmutable $now = new \DateTimeImmutable())
    {
    }

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
        $entities = $input->getOption(self::OPTION_NAME);

        if (!empty($entities)) {
            $this->processEntities($config, $entities);

            return;
        }

        $entities = $this->askForEntities($io);

        if (empty($entities)) {
            return;
        }

        $this->processEntities($config, $entities);
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME)
            || empty($configuration->getOption(self::OPTION_NAME))
            || !\is_array($configuration->getOption(self::OPTION_NAME))
        ) {
            return;
        }

        foreach ($configuration->getOption(self::OPTION_NAME) as $entityName) {
            $stubCollection->add($this->createMigration($configuration, $entityName));
            $stubCollection->add($this->createEntityClass($configuration, $entityName));
            $stubCollection->add($this->createEntityDefinition($configuration, $entityName));
            $stubCollection->add($this->createEntityCollection($configuration, $entityName));

            $stubCollection->append(
                'src/Resources/config/services.xml',
                str_replace(
                    ['{{ namespace }}', '{{ entityName }}', '{{ tableName }}'],
                    [$configuration->namespace, $entityName, $this->getTableName($entityName)],
                    $this->servicesXmlEntry
                )
            );
        }
    }

    private function createMigration(PluginScaffoldConfiguration $configuration, string $entityName): Stub
    {
        $tableName = $this->getTableName($entityName);
        $timeStamp = (string) $this->now->getTimestamp();

        $migrationPath = sprintf(
            'src/Migration/Migration%sCreate%sTable.php',
            $timeStamp,
            $entityName
        );

        return Stub::template(
            $migrationPath,
            self::STUB_DIRECTORY . '/migration.stub',
            [
                'namespace' => $configuration->namespace,
                'entityName' => $entityName,
                'tableName' => $tableName,
                'timestamp' => $timeStamp,
            ]
        );
    }

    private function createEntityClass(PluginScaffoldConfiguration $configuration, string $entityName): Stub
    {
        $entityClassPath = sprintf(
            'src/Core/Content/%s/%sEntity.php',
            $entityName,
            $entityName
        );

        return Stub::template(
            $entityClassPath,
            self::STUB_DIRECTORY . '/entity.stub',
            [
                'namespace' => $configuration->namespace,
                'entityName' => $entityName,
            ]
        );
    }

    private function createEntityDefinition(PluginScaffoldConfiguration $configuration, string $entityName): Stub
    {
        $tableName = $this->getTableName($entityName);

        $entityDefinitionPath = sprintf(
            'src/Core/Content/%s/%sDefinition.php',
            $entityName,
            $entityName
        );

        return Stub::template(
            $entityDefinitionPath,
            self::STUB_DIRECTORY . '/entity-definition.stub',
            [
                'namespace' => $configuration->namespace,
                'entityName' => $entityName,
                'tableName' => $tableName,
            ]
        );
    }

    private function createEntityCollection(PluginScaffoldConfiguration $configuration, string $entityName): Stub
    {
        $entityCollectionPath = sprintf(
            'src/Core/Content/%s/%sCollection.php',
            $entityName,
            $entityName
        );

        return Stub::template(
            $entityCollectionPath,
            self::STUB_DIRECTORY . '/entity-collection.stub',
            [
                'namespace' => $configuration->namespace,
                'entityName' => $entityName,
            ]
        );
    }

    private function getTableName(string $entityName): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($entityName);
    }

    private function askForEntities(SymfonyStyle $io): ?string
    {
        $entitiesProvided = $io->confirm('Do you want to create entities?');

        if (!$entitiesProvided) {
            return null;
        }

        return $io->ask('Please provide a list of entities (PascalCase, comma separated)');
    }

    private function processEntities(PluginScaffoldConfiguration $config, string $entities): void
    {
        $parsed = $this->parseEntities($entities);

        if (!empty($parsed)) {
            $config->addOption(self::OPTION_NAME, $this->parseEntities($entities));
        }
    }

    /**
     * @return string[]
     */
    private function parseEntities(string $entities): array
    {
        $entities = str_replace([' ', '\\', '"', '\''], '', $entities);

        $entities = explode(',', $entities);

        return array_filter(array_map(function (string $entity) {
            return ucfirst(trim($entity));
        }, $entities));
    }
}
