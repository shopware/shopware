<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Inspection\ArrayWriter;
use Shopware\Docs\Inspection\ModuleInspector;
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

 **Data store**
  : These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.
  
**Maintenance**
  : Provide commands executable through CLI to trigger maintenance tasks.
  
**Custom actions**
  : These modules contain more then simple CRUD actions. They provide special actions and services that ease management and additionally check consistency.
  
**SalesChannel-API**
 : These modules provide logic through a sales channel for the storefront.
 
**Custom Extendable**
 : These modules contain interfaces, process container tags or provide custom events as extension points.
  
**Rule Provider**
  : Cross-system process to validate workflow decisions. 
  
**Business Event Dispatcher**
  : Provide special events to handle business cases.
 
**Extension**
  : These modules contain extensions of - usually Framework - interfaces and classes to provide more specific functions for the Platform. 

%s
EOD;

    const TEMPLATE_MODULE = <<<EOD
### [%s](https://github.com/shopware/platform/tree/master/src/Core/%s) 
%s

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

        /** @var SplFileInfo $moduleDirectory */
        foreach ($finder as $moduleDirectory) {
            $tags = $this->moduleInspector->inspectModule($moduleDirectory);
            $modulePathName = $moduleDirectory->getRelativePathname();

            $descriptions->ensure($modulePathName);
            $markdown[$modulePathName] = sprintf(
                self::TEMPLATE_MODULE,
                $modulePathName,
                $modulePathName,
                implode(', ', $tags),
                $descriptions->get($modulePathName)
            );

            $io->write($modulePathName . ': ' . implode(', ', $tags) . PHP_EOL);
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
            ->filter(function (SplFileInfo $fileInfo) {
                return !in_array($fileInfo->getRelativePathname(), [
                    'Profiling/DependencyInjection',
                    'Migration/Test',
                    'Profiling/Entity',
                    'Profiling/Checkout',
                    'Framework/DependencyInjection',
                    'Framework/Test',
                    'System/DependencyInjection',
                    'System/Test',
                    'Checkout/DependencyInjection',
                    'Checkout/Test',
                    'Content/DependencyInjection',
                    'Content/Test',
                    'Checkout/Resources',
                    'Checkout/Util',
                    'Checkout/Document',
                    'Checkout/Exception',
                    'System/Command',
                    'System/Event',
                    'System/Listing',
                    'System/Exception',
                    'Framework/Command',
                    'Framework/Resources',
                    'Framework/Faker',
                    'Framework/Util',
                    'Framework/Provisioning',
                    'Framework/Exception',
                    'Framework/Demodata',
                    'Profiling/Doctrine',
                    'Profiling/Resources',
                    'Profiling/Twig',
                    'Checkout/Promotion',
                    'Framework/Version',
                    'Content/MailTemplate',
                    'Content/DeliveryTime',
                ], true);
            })
            ->depth('1');
    }
}
