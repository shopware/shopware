<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'dal:migration:create',
    description: 'Creates migration for entity schema',
)]
class CreateMigrationCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly MigrationQueryGenerator $queryGenerator,
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
        private readonly MigrationFileRenderer $migrationFileRenderer,
        private readonly string $coreDir,
        private readonly string $shopwareVersion,
        private readonly ClockInterface $clock
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entities', InputArgument::REQUIRED, 'Entities, comma separated')
            ->addOption('namespace', null, InputArgument::OPTIONAL, 'Namespace (eg. V6_5)')
            ->addOption('bundle', null, InputArgument::OPTIONAL, 'Bundle name (plugin name)')
            ->addOption('package', null, InputArgument::OPTIONAL, 'The package name for the migration')
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED, 'Plugin name, used instead of --bundle and --namespace. A unique prefix is enough')
            ->addOption('auto-translation', null, InputOption::VALUE_NONE, 'Also create migrations for the translation definitions of the given entities')
            ->addOption('auto-mapping', null, InputOption::VALUE_NONE, 'Also create migrations for the many-to-many mapping definitions of the given entities');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timestamp = $this->clock->now()->getTimestamp();

        $bundle = $this->getBundle($input);
        $namespace = $this->getNamespace($input, $bundle);
        $directory = $this->getDirectory($input, $bundle);
        $package = $input->getOption('package') ?? 'core';

        $io = new SymfonyStyle($input, $output);

        $io->title('DAL generate migration');

        $definitions = [];
        foreach (explode(',', $input->getArgument('entities')) as $entity) {
            $definitions[$entity] = $this->registry->getByEntityName($entity);
        }

        $related = $this->getRelatedDefinitions(
            $definitions,
            (bool) $input->getOption('auto-translation'),
            (bool) $input->getOption('auto-mapping')
        );

        foreach ($definitions as $entity => $definition) {
            $this->handleEntity($entity, $definition, (string) $timestamp, $namespace, $directory, $package, $io);
        }

        // Translation and mapping tables reference the tables above, so their migrations must run afterwards
        foreach (array_values($related) as $offset => $definition) {
            $this->handleEntity($definition->getEntityName(), $definition, (string) ($timestamp + $offset + 1), $namespace, $directory, $package, $io);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     *
     * @return array<string, EntityDefinition>
     */
    private function getRelatedDefinitions(array $definitions, bool $translations, bool $mappings): array
    {
        $translationDefinitions = [];
        $mappingDefinitions = [];

        foreach ($definitions as $definition) {
            if ($translations && $translationDefinition = $definition->getTranslationDefinition()) {
                $translationDefinitions[$translationDefinition->getEntityName()] = $translationDefinition;
            }

            if (!$mappings) {
                continue;
            }

            foreach ($definition->getFields()->filterInstance(ManyToManyAssociationField::class) as $field) {
                \assert($field instanceof ManyToManyAssociationField);
                $mappingDefinition = $field->getMappingDefinition();
                $mappingDefinitions[$mappingDefinition->getEntityName()] = $mappingDefinition;
            }
        }

        return array_diff_key($translationDefinitions + $mappingDefinitions, $definitions);
    }

    private function getBundle(InputInterface $input): ?Bundle
    {
        $pluginName = $input->getOption('plugin');

        if ($pluginName) {
            return $this->getPlugin($pluginName);
        }

        $bundleName = $input->getOption('bundle');

        if (!$bundleName) {
            return null;
        }

        /** @var Bundle $bundle */
        $bundle = $this->kernel->getBundle($bundleName);

        return $bundle;
    }

    private function getPlugin(string $pluginName): Plugin
    {
        $plugins = array_filter(
            $this->kernel->getBundles(),
            static fn ($bundle) => $bundle instanceof Plugin && str_starts_with($bundle->getName(), $pluginName)
        );

        if ($plugins === []) {
            throw MigrationException::pluginNotFound($pluginName);
        }

        if (\count($plugins) > 1) {
            $exactMatches = array_filter($plugins, static fn (Plugin $plugin) => $plugin->getName() === $pluginName);

            if (\count($exactMatches) !== 1) {
                throw MigrationException::moreThanOnePluginFound($pluginName, array_map(static fn (Plugin $plugin) => $plugin->getName(), array_values($plugins)));
            }

            $plugins = $exactMatches;
        }

        return array_values($plugins)[0];
    }

    private function getNamespace(InputInterface $input, ?Bundle $bundle): string
    {
        if ($bundle === null) {
            return 'Shopware\\Core\\Migration\\' . $this->getNamespaceFolder($input);
        }

        return $bundle->getMigrationNamespace();
    }

    private function getDirectory(InputInterface $input, ?Bundle $bundle): string
    {
        if ($bundle === null) {
            return $this->coreDir . '/Migration/' . $this->getNamespaceFolder($input);
        }

        $directory = $bundle->getMigrationPath();

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        return $directory;
    }

    private function getNamespaceFolder(InputInterface $input): string
    {
        $namespace = $input->getOption('namespace');

        if ($namespace !== null) {
            return $namespace;
        }

        [$_, $major] = explode('.', $this->shopwareVersion);

        return 'V6_' . $major;
    }

    private function handleEntity(
        string $entity,
        EntityDefinition $entityDefinition,
        string $timestamp,
        string $namespace,
        string $directory,
        string $package,
        SymfonyStyle $io
    ): void {
        $io->info('Processing entity: ' . $entity);

        $queries = $this->queryGenerator->generateQueries($entityDefinition);

        if ($queries !== []) {
            $path = Path::join($directory, MigrationFileRenderer::createMigrationClassName($timestamp, $entity) . '.php');

            $className = MigrationFileRenderer::createMigrationClassName($timestamp, $entity);
            $content = $this->migrationFileRenderer->render($namespace, $className, $timestamp, $queries, $package);

            $this->filesystem->dumpFile($path, $content);

            $io->success('Migration file created: ' . $path);
        } else {
            $io->note('No changes detected. No migration file created.');
        }
    }
}
