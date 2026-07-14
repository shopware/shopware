<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\FulltextSearchRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'dbal:fulltext-search',
    description: 'Enable fulltext search by adding FULLTEXT indexes to searchable fields',
)]
#[Package('framework')]
class FulltextSearchCommand extends Command
{
    /**
     * @var array<string, bool>
     */
    private array $fulltextIndexCache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly Connection $connection,
        private readonly FulltextSearchRegistry $fulltextRegistry
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Enables fulltext search by dynamically adding FULLTEXT indexes to StringFields with SearchRanking flags');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        // Check MySQL compatibility
        if (!$this->isMySQLCompatible()) {
            $io->error([
                'This feature is only supported for MySQL or MariaDB databases.',
                'Current database platform is not supported.',
            ]);
            return Command::FAILURE;
        }

        // Show warning about irreversibility
        $io->warning([
            '⚠️  This action modifies database indexes and cannot be rolled back automatically.',
            '',
            'MySQL Fulltext Configuration Notes:',
            '• Default MySQL stopword list may ignore common words (e.g., "a", "the")',
            '• Words shorter than 4 characters are ignored by default unless innodb_ft_min_token_size is changed',
            '• This will improve search performance for LIKE %...% queries by replacing them with MATCH...AGAINST',
        ]);

        if (!$io->confirm('Do you want to continue?', false)) {
            $io->note('Operation cancelled.');
            return Command::SUCCESS;
        }

        $io->title('Enabling Fulltext Search');

        // First, discover and persist all existing fulltext indexes
        $this->discoverAndPersistExistingFulltextIndexes($io);

        // Detect eligible fields
        $eligibleFields = $this->detectEligibleFields($io);

        if (empty($eligibleFields)) {
            $io->success('No eligible fields found for fulltext indexing.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Found %d eligible fields for fulltext indexing.', count($eligibleFields)));

        // Add FULLTEXT indexes
        $indexedFields = $this->addFulltextIndexes($eligibleFields, $io);

        // Store state in registry
        $this->storeIndexedFieldsState($indexedFields, $io);

        $io->success([
            sprintf('Successfully indexed %d fields for fulltext search.', count($indexedFields)),
            'Fields with FULLTEXT indexes will now use MATCH...AGAINST instead of LIKE queries.',
        ]);

        return Command::SUCCESS;
    }

    private function isMySQLCompatible(): bool
    {
        $platform = $this->connection->getDatabasePlatform();
        return $platform instanceof MySQLPlatform;
    }

    /**
     * @return array<string, array{entity: string, field: string, table: string, column: string}>
     */
    private function detectEligibleFields(ShopwareStyle $io): array
    {
        $io->section('Detecting eligible fields...');

        $eligibleFields = [];
        $definitions = $this->definitionRegistry->getDefinitions();

        foreach ($definitions as $definition) {
            if ($definition instanceof EntityTranslationDefinition) {
                continue;
            }

            $fields = $definition->getFields();

            foreach ($fields as $field) {
                if (!$field->is(SearchRanking::class)) {
                    continue;
                }

                $tableName = $definition->getEntityName();
                $entityName = $definition->getEntityName();

                if ($field instanceof TranslatedField) {
                    try {
                        $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                        $translationDefinition = $definition->getTranslationDefinition();

                        $tableName = $translationDefinition->getEntityName();
                        $entityName = $translationDefinition->getEntityName();
                    } catch (\Throwable $exception) {
                        dd($definition->getEntityName(), $exception->getMessage(), $definition instanceof EntityTranslationDefinition);
                    }
                }


                // Check if it's a StringField with SearchRanking flag
                if (!$field instanceof StringField) {
                    continue;
                }

                $fieldName = $field->getPropertyName();
                $columnName = $field->getStorageName();

                // Skip if already has FULLTEXT index
                if ($this->hasFulltextIndex($tableName, $columnName)) {
                    $io->writeln(sprintf('  <comment>Skipping %s.%s (already has FULLTEXT index)</comment>', $entityName, $fieldName));
                    continue;
                }

                $key = sprintf('%s.%s', $entityName, $fieldName);
                $eligibleFields[$key] = [
                    'entity' => $entityName,
                    'field' => $fieldName,
                    'table' => $tableName,
                    'column' => $columnName,
                ];

                $io->writeln(sprintf('  <info>Found eligible field: %s.%s</info>', $entityName, $fieldName));
            }
        }

        return $eligibleFields;
    }

    private function hasFulltextIndex(string $tableName, string $columnName): bool
    {
        $cacheKey = sprintf('%s.%s', $tableName, $columnName);
        
        if (array_key_exists($cacheKey, $this->fulltextIndexCache)) {
            return $this->fulltextIndexCache[$cacheKey];
        }

        try {
            $schemaManager = $this->connection->createSchemaManager();
            
            if (!$schemaManager->tablesExist([$tableName])) {
                return $this->fulltextIndexCache[$cacheKey] = false;
            }

            $table = $schemaManager->introspectTable($tableName);
            $indexes = $table->getIndexes();

            foreach ($indexes as $index) {
                if ($index->hasFlag('fulltext') && in_array($columnName, $index->getColumns(), true)) {
                    return $this->fulltextIndexCache[$cacheKey] = true;
                }
            }

            return $this->fulltextIndexCache[$cacheKey] = false;
        } catch (\Exception) {
            return $this->fulltextIndexCache[$cacheKey] = false;
        }
    }

    /**
     * @param array<string, array{entity: string, field: string, table: string, column: string}> $eligibleFields
     * @return array<string>
     */
    private function addFulltextIndexes(array $eligibleFields, ShopwareStyle $io): array
    {
        $io->section('Adding FULLTEXT indexes...');

        $indexedFields = [];

        foreach ($eligibleFields as $key => $fieldInfo) {
            try {
                $indexName = sprintf('ft_%s_%s', $fieldInfo['table'], $fieldInfo['column']);
                $sql = sprintf(
                    'ALTER TABLE `%s` ADD FULLTEXT KEY `%s` (`%s`)',
                    $fieldInfo['table'],
                    $indexName,
                    $fieldInfo['column']
                );

                $this->connection->executeStatement($sql);
                $indexedFields[] = $key;

                $io->writeln(sprintf('  <info>✓ Added FULLTEXT index for %s.%s</info>', $fieldInfo['entity'], $fieldInfo['field']));
            } catch (\Exception $e) {
                $io->writeln(sprintf('  <error>✗ Failed to add FULLTEXT index for %s.%s: %s</error>', 
                    $fieldInfo['entity'], 
                    $fieldInfo['field'], 
                    $e->getMessage()
                ));
            }
        }

        return $indexedFields;
    }

    /**
     * @param array<string> $indexedFields
     */
    private function storeIndexedFieldsState(array $indexedFields, ShopwareStyle $io): void
    {
        $io->section('Storing indexed fields state...');

        if (!empty($indexedFields)) {
            $this->fulltextRegistry->addIndexedFields($indexedFields);
        }

        $io->writeln(sprintf('  <info>✓ Stored state for %d indexed fields</info>', count($indexedFields)));
    }

    private function discoverAndPersistExistingFulltextIndexes(ShopwareStyle $io): void
    {
        $io->section('Discovering existing FULLTEXT indexes...');

        $existingIndexedFields = [];
        $definitions = $this->definitionRegistry->getDefinitions();

        foreach ($definitions as $definition) {
            if ($definition instanceof EntityTranslationDefinition) {
                continue;
            }

            $fields = $definition->getFields();

            foreach ($fields as $field) {
                if (!$field->is(SearchRanking::class)) {
                    continue;
                }

                $tableName = $definition->getEntityName();
                $entityName = $definition->getEntityName();

                if ($field instanceof TranslatedField) {
                    try {
                        $field = EntityDefinitionQueryHelper::getTranslatedField($definition, $field);
                        $translationDefinition = $definition->getTranslationDefinition();

                        $tableName = $translationDefinition->getEntityName();
                        $entityName = $translationDefinition->getEntityName();
                    } catch (\Throwable) {
                        continue;
                    }
                }

                // Check if it's a StringField with SearchRanking flag
                if (!$field instanceof StringField) {
                    continue;
                }

                $fieldName = $field->getPropertyName();
                $columnName = $field->getStorageName();

                // Check if this field already has FULLTEXT index
                if ($this->hasFulltextIndex($tableName, $columnName)) {
                    $key = sprintf('%s.%s', $entityName, $fieldName);
                    $existingIndexedFields[] = $key;
                    $io->writeln(sprintf('  <info>Found existing FULLTEXT index: %s.%s</info>', $entityName, $fieldName));
                }
            }
        }

        if (!empty($existingIndexedFields)) {
            $this->fulltextRegistry->addIndexedFields($existingIndexedFields);
            $io->writeln(sprintf('  <info>✓ Persisted %d existing FULLTEXT indexes to registry</info>', count($existingIndexedFields)));
        } else {
            $io->writeln('  <comment>No existing FULLTEXT indexes found</comment>');
        }
    }
} 