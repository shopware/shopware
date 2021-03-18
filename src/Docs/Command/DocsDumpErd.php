<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Docs\Inspection\ArrayWriter;
use Shopware\Docs\Inspection\ErdDefinition;
use Shopware\Docs\Inspection\ErdGenerator;
use Shopware\Docs\Inspection\MarkdownErdDumper;
use Shopware\Docs\Inspection\PlantUmlErdDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class DocsDumpErd extends Command
{
    protected static $defaultName = 'docs:dump-erd';

    /**
     * @var array<class-string>
     */
    private array $ignoredDefinitions = [
        VersionCommitDataDefinition::class,
        VersionCommitDefinition::class,
        VersionDefinition::class,
    ];

    private DefinitionInstanceRegistry $registry;

    private ErdGenerator $erdGenerator;

    public function __construct(
        DefinitionInstanceRegistry $registry,
        ErdGenerator $erdGenerator
    ) {
        parent::__construct();
        $this->registry = $registry;
        $this->erdGenerator = $erdGenerator;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Dump an entity relationship diagram');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $descriptionsShort = new ArrayWriter(__DIR__ . '/../Resources/erd-short-description.php');
        $descriptionsLong = new ArrayWriter(__DIR__ . '/../Resources/erd-long-description.php');
        $destPath = __DIR__ . '/../Resources/current/60-references-internals/10-core/10-erd';

        $fs = new Filesystem();
        $fs->remove(glob($destPath . '/erd-*'));
        $fs->remove($destPath . '/_puml');
        $fs->mkdir($destPath . '/_puml');

        $definitions = $this->loadDefinitions();
        $modules = $this->sortDefinitionsIntoModules($definitions);

        $this->updateTranslations($definitions, $descriptionsLong, $descriptionsShort, $modules);

        $io->listing(array_keys($modules));

        $this->generateModuleErd($modules, $descriptionsShort, $destPath, $descriptionsLong);
        $this->generateGlobalErd($modules, $descriptionsShort, $destPath, $definitions);

        return 0;
    }

    protected function updateTranslations(array $definitions, ArrayWriter $descriptionsLong, ArrayWriter $descriptionsShort, array $modules): void
    {
        foreach ($definitions as $definition) {
            if ($definition->isMapping()) {
                $descriptionsLong->set($definition->toClassName(), '');
                $descriptionsShort->set($definition->toClassName(), 'M:N Mapping');

                continue;
            }

            if ($definition->isTranslation()) {
                $descriptionsLong->set($definition->toClassName(), '');
                $descriptionsShort->set($definition->toClassName(), 'Translations');

                continue;
            }

            $descriptionsShort->ensure($definition->toClassName());
            $descriptionsLong->ensure($definition->toClassName());
        }

        foreach (array_keys($modules) as $moduleName) {
            $descriptionsLong->ensure($moduleName);
            $descriptionsShort->ensure($moduleName);
        }

        $descriptionsShort->dump();
        $descriptionsLong->dump(true);
    }

    protected function generateModuleErd(array $modules, ArrayWriter $descriptionsShort, string $destPath, ArrayWriter $descriptionsLong): void
    {
        /*
         * @var ErdDefinition[]
         */
        foreach ($modules as $moduleName => $moduleDefinition) {
            $fileName = $this->toFileName($moduleName);
            $dump = $this->erdGenerator->generateFromDefinitions(
                $moduleDefinition,
                new PlantUmlErdDumper(),
                $descriptionsShort
            );
            file_put_contents(
                $destPath . '/_puml/erd-' . $fileName . '.puml',
                $dump
            );

            $dump = $this->erdGenerator->generateFromDefinitions(
                $moduleDefinition,
                new MarkdownErdDumper(
                    $descriptionsShort->get($moduleName),
                    $this->toHash($moduleName),
                    $descriptionsLong->get($moduleName),
                    'dist/erd-' . $fileName . '.png'
                ),
                $descriptionsLong
            );
            file_put_contents(
                $destPath . '/erd-' . $fileName . '.md',
                $dump
            );
        }
    }

    private function toFileName(string $moduleName): string
    {
        return mb_strtolower(str_replace('\\', '-', $moduleName));
    }

    private function toHash(string $moduleName): string
    {
        $hash = mb_strtolower(str_replace('\\', '_', $moduleName));
        $hash = str_replace(
            ['shopware_core', 'shopware_storefront'],
            ['internals_core_erd', 'internals_storefront_erd'],
            $hash
        );

        return $hash;
    }

    /**
     * @param ErdDefinition[] $definitions
     */
    private function sortDefinitionsIntoModules(array $definitions): array
    {
        $modules = [];

        foreach ($definitions as $definition) {
            $moduleName = $definition->toModuleName();

            if (!isset($modules[$moduleName])) {
                $modules[$moduleName] = [];
            }

            $modules[$moduleName][] = $definition;
        }

        return $modules;
    }

    /**
     * @return ErdDefinition[]
     */
    private function loadDefinitions(): array
    {
        $definitions = $this->registry->getDefinitions();

        $definitions = array_filter($definitions, function (EntityDefinition $definition) {
            return !\in_array($definition->getClass(), $this->ignoredDefinitions, true);
        });

        return array_map(static function (EntityDefinition $definition) {
            return new ErdDefinition($definition);
        }, $definitions);
    }

    private function generateGlobalErd(array $modules, ArrayWriter $descriptionsShort, string $destPath, array $definitions): void
    {
        $dump = $this->erdGenerator->generateFromModules($modules, new PlantUmlErdDumper(), $descriptionsShort);
        file_put_contents(
            $destPath . '/_puml/erd-overview.puml',
            $dump
        );

        $dump = $this->erdGenerator->generateFromDefinitions($definitions, new PlantUmlErdDumper(), $descriptionsShort);
        file_put_contents(
            $destPath . '/_puml/erd-all.puml',
            $dump
        );
    }
}
