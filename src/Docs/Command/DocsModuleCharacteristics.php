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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Dumping modules file');
        $characteristics = $this->loadCharacteristics();
        (new TemplateModuleFile($this->moduleInspector))->renderModuleList($characteristics, $io);

        $io->section('Dumping rules file');
        (new TemplateCustomRulesList($this->moduleInspector))->render($characteristics);

        $io->success('Done');

        return null;
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
                    'System/Resources',
                    'Content/Resources',

                    'Migration/Test',
                    'Framework/Test',
                    'Checkout/Test',
                    'System/Test',
                    'Content/Test',

                    'Profiling/Entity',
                    'Profiling/Checkout',
                    'Checkout/Util',
                    'Checkout/Document',
                    'Checkout/Exception',

                    'System/Listing',
                    'System/Exception',

                    'Framework/Command',
                    'Framework/Resources',
                    'Framework/Faker',
                    'Framework/Util',
                    'Framework/Provisioning',
                    'Framework/Exception',
                    'Framework/Demodata',
                    'Framework/Version',

                    'Profiling/Doctrine',
                    'Profiling/Resources',
                    'Profiling/Twig',
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
