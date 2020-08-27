<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Inspection\CharacteristicsCollection;
use Shopware\Docs\Inspection\ModuleInspector;
use Shopware\Docs\Inspection\TemplateCustomRulesList;
use Shopware\Docs\Inspection\TemplateModuleFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DocsModuleCharacteristics extends Command
{
    protected static $defaultName = 'docs:dump-core-characteristics';

    /**
     * @var ModuleInspector
     */
    private $moduleInspector;

    public function __construct(ModuleInspector $moduleInspector)
    {
        parent::__construct();
        $this->moduleInspector = $moduleInspector;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Dump the characteristics of core modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Dumping modules file');
        $characteristics = $this->loadCharacteristics();
        (new TemplateModuleFile($this->moduleInspector))->renderModuleList($characteristics, $io);

        $io->section('Dumping rules file');
        (new TemplateCustomRulesList($this->moduleInspector))->render($characteristics);

        $io->success('Done');

        return 0;
    }

    private function createModuleFinder(): Finder
    {
        return (new Finder())
            ->directories()
            ->in(__DIR__ . '/../../Core')
            ->sortByName()
            ->filter(static function (SplFileInfo $fileInfo) {
                return !\in_array($fileInfo->getRelativePathname(), [
                    'Profiling/DependencyInjection',
                    'Framework/DependencyInjection',
                    'System/DependencyInjection',
                    'Checkout/DependencyInjection',
                    'Content/DependencyInjection',

                    'Checkout/Resources',
                    'Content/Resources',
                    'Framework/Resources',
                    'Profiling/Resources',
                    'System/Resources',

                    'Migration/Test',
                    'Framework/Test',
                    'Checkout/Test',
                    'System/Test',
                    'Content/Test',
                    'Profiling/Test',

                    'Profiling/Checkout',
                    'Profiling/Doctrine',
                    'Profiling/Entity',
                    'Profiling/Twig',

                    'Framework/Util',
                    'Framework/Demodata',

                    'Migration/Fixtures',
                ], true);
            })
            ->depth('1');
    }

    private function loadCharacteristics(): CharacteristicsCollection
    {
        $finder = $this->createModuleFinder();
        $characteristics = new CharacteristicsCollection();
        /** @var SplFileInfo $moduleDirectory */
        foreach ($finder as $moduleDirectory) {
            $tags = $this->moduleInspector->inspectModule($moduleDirectory);
            $characteristics->add($tags);
        }

        return $characteristics;
    }
}
