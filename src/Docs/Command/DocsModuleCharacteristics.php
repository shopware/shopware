<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Inspection\ArrayWriter;
use Shopware\Docs\Inspection\ModuleInspector;
use Shopware\Docs\Inspection\ModuleTag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DocsModuleCharacteristics extends Command
{
    const TEMPLATE_HEADER = <<<EOD
[titleEn]: <>(Core Module List)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this. The following list provides a rough overview what domain concepts offer what kinds of interfaces.  

## Possible characteristics

<span class="tip is--primary">Data store</span>
  : These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.
  
<span class="tip is--primary">Maintenance</span>
  : Provide commands executable through CLI to trigger maintenance tasks.
  
<span class="tip is--primary">Custom actions</span>
  : These modules contain more then simple CRUD actions. They provide special actions and services that ease management and additionally check consistency.
  
<span class="tip is--primary">SalesChannel-API</span>
 : These modules provide logic through a sales channel for the storefront.
 
<span class="tip is--primary">Custom Extendable</span>
 : These modules contain interfaces, process container tags or provide custom events as extension points.
  
<span class="tip is--primary">Rule Provider</span>
  : Cross-system process to validate workflow decisions. 
  
<span class="tip is--primary">Business Event Dispatcher</span>
  : Provide special events to handle business cases.
 
<span class="tip is--primary">Extension</span>
  : These modules contain extensions of - usually Framework - interfaces and classes to provide more specific functions for the Platform. 
  
<span class="tip is--primary">Custom Rules</span>
  : Provides rules for the rule system used by the checkout.

## Modules

%s
EOD;

    const TEMPLATE_MODULE = <<<EOD
#### %s %s

* [Sources]((https://github.com/shopware/platform/tree/master/src/Core/%s)) 

%s

EOD;

    /**
     * @var string
     */
    private $descriptionPath = __DIR__ . '/../Resources/characteristics-module-descriptions.php';

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
            ->setName('docs:dump-core-characteristics')
            ->setDescription('Dump the characteristics of core modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $descriptions = new ArrayWriter($this->descriptionPath);
        $finder = $this->createModuleFinder();

        $markdown = [];

        $allTags = [[]];
        /** @var SplFileInfo $moduleDirectory */
        foreach ($finder as $moduleDirectory) {
            $tags = $this->moduleInspector->inspectModule($moduleDirectory);
            $modulePathName = $moduleDirectory->getRelativePathname();
            [$bundleName, $moduleName] = explode('/', $modulePathName);

            $tagNames = array_map(function (ModuleTag $tag) { return $tag->name(); }, $tags);
            $renderedTags = array_map(function (string $tagName) { return sprintf('<span class="tip is--primary">%s</span>', $tagName); }, $tagNames);

            $descriptions->ensure($modulePathName);
            $markdown[$bundleName] = sprintf('### %s Bundle' . PHP_EOL, $bundleName);

            $markdown[$modulePathName] = sprintf(
                self::TEMPLATE_MODULE,
                $moduleName,
                implode(' ', $renderedTags),
                $modulePathName,
                $descriptions->get($modulePathName)
            );

            $io->write($modulePathName . ': ' . implode(', ', $tagNames) . PHP_EOL);
        }

        $descriptions->dump(true);
        file_put_contents(__DIR__ . '/../_new/2-internals/1-core/10-modules.md', sprintf(self::TEMPLATE_HEADER, implode(PHP_EOL, $markdown)));

        $io->success('Done');
    }

    private function createModuleFinder(): Finder
    {
        return (new Finder())
            ->directories()
            ->in(__DIR__ . '/../../Core')
            ->sortByName()
            ->filter(function (SplFileInfo $fileInfo) {
                return !in_array($fileInfo->getRelativePathname(), [
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
}
