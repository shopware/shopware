<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'dal:migration:create',
    description: 'Creates migration for entity schema',
)]
#[Package('core')]
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
        private readonly \DateTimeImmutable $now = new \DateTimeImmutable()
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entities', InputArgument::REQUIRED, 'Entities, comma separated')
            ->addOption('namespace', null, InputArgument::OPTIONAL, 'Namespace (eg. V6_5)')
            ->addOption('bundle', null, InputArgument::OPTIONAL, 'Bundle name (plugin name)')
            ->addOption('package', null, InputArgument::OPTIONAL, 'The package name for the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timestamp = (string) $this->now->getTimestamp();

        $namespace = $this->getNamespace($input);
        $directory = $this->getDirectory($input);
        $package = $input->getOption('package') ?? 'core';

        $io = new ShopwareStyle($input, $output);

        $io->title('DAL generate migration');

        $entities = explode(',', $input->getArgument('entities'));

        foreach ($entities as $entity) {
            $this->handleEntity($entity, $timestamp, $namespace, $directory, $package, $io);
        }

        return self::SUCCESS;
    }

    private function getNamespace(InputInterface $input): string
    {
        $bundleName = $input->getOption('bundle');

        if (!$bundleName) {
            return 'Shopware\\Core\\Migration\\' . $this->getNamespaceFolder($input);
        }

        /** @var Bundle $bundle */
        $bundle = $this->kernel->getBundle($bundleName);

        return $bundle->getMigrationNamespace();
    }

    private function getDirectory(InputInterface $input): string
    {
        $bundleName = $input->getOption('bundle');

        if (!$bundleName) {
            return $this->coreDir . '/Migration/' . $this->getNamespaceFolder($input);
        }

        /** @var Bundle $bundle */
        $bundle = $this->kernel->getBundle($bundleName);

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
        string $timestamp,
        string $namespace,
        string $directory,
        string $package,
        ShopwareStyle $io
    ): void {
        $io->info('Processing entity: ' . $entity);

        $entityDefinition = $this->registry->getByEntityName($entity);

        $queries = $this->queryGenerator->generateQueries($entityDefinition);

        if (!empty($queries)) {
            $path = $directory . '/' . MigrationFileRenderer::createMigrationClassName($timestamp, $entity) . '.php';

            $className = MigrationFileRenderer::createMigrationClassName($timestamp, $entity);
            $content = $this->migrationFileRenderer->render($namespace, $className, $timestamp, $queries, $package);

            $this->filesystem->dumpFile($path, $content);

            $io->success('Migration file created: ' . $path);
        } else {
            $io->note('No changes detected. No migration file created.');
        }
    }
}
