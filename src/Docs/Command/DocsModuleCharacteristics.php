<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Inspection\ArrayWriter;
use Shopware\Docs\Inspection\ModuleInspector;
use Shopware\Docs\Inspection\ModuleTag;
use Shopware\Docs\Inspection\ModuleTagCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DocsModuleCharacteristics extends Command
{
    const TEMPLATE_PAGE = <<<EOD
[titleEn]: <>(Core Module List)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this. The following list provides a rough overview what domain concepts offer what kinds of interfaces.  

## Possible characteristics

%s

## Modules

%s
EOD;

    const TEMPLATE_TAG = <<<EOD
<span class="tip is--primary">%s</span>
  : %s

EOD;

    const TEMPLATE_MODULE = <<<EOD
#### %s %s

* [Sources]((https://github.com/shopware/platform/tree/master/src/Core/%s)) 

%s

EOD;

    /**
     * @var string
     */
    private $moduleDescriptionPath = __DIR__ . '/../Resources/characteristics-module-descriptions.php';

    /**
     * @var string
     */
    private $tagDescriptionPath = __DIR__ . '/../Resources/characteristics-tag-descriptions.php';

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

        $tagCollection = new ModuleTagCollection();

        $characteristics = $this->loadChracteristics($tagCollection);

        $renderdTags = $this->renderTags([]);
        $renderdModules = $this->renderCharacteristics($characteristics, [], $io);
        file_put_contents(
            __DIR__ . '/../_new/2-internals/1-core/10-modules.md',
            sprintf(
                self::TEMPLATE_PAGE,
                implode(PHP_EOL, $renderdTags),
                implode(PHP_EOL, $renderdModules)
        ));

//        print_r($tagCollection->filterName('Custom Rules'));

        $io->success('Done');
    }

    protected function renderCharacteristics(array $characteristics, array $markdown, SymfonyStyle $io): array
    {
        $moduleDescriptions = new ArrayWriter($this->moduleDescriptionPath);
        foreach ($characteristics as $modulePathName => $tags) {
            [$bundleName, $moduleName] = explode('/', $modulePathName);

            $tagNames = array_map(function (ModuleTag $tag) {
                return $tag->name();
            }, $tags);
            $renderedTags = array_map(function (string $tagName) {
                return sprintf('<span class="tip is--primary">%s</span>', $tagName);
            }, $tagNames);

            $moduleDescriptions->ensure($modulePathName);
            $markdown[$bundleName] = sprintf('### %s Bundle' . PHP_EOL, $bundleName);

            $markdown[$modulePathName] = sprintf(
                self::TEMPLATE_MODULE,
                $moduleName,
                implode(' ', $renderedTags),
                $modulePathName,
                $moduleDescriptions->get($modulePathName)
            );

            $io->write($modulePathName . ': ' . implode(', ', $tagNames) . PHP_EOL);
        }

        $moduleDescriptions->dump(true);

        return $markdown;
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

    private function loadChracteristics(ModuleTagCollection $tagCollection): array
    {
        $finder = $this->createModuleFinder();
        $characteristics = [];
        /** @var SplFileInfo $moduleDirectory */
        foreach ($finder as $moduleDirectory) {
            $tags = $this->moduleInspector->inspectModule($moduleDirectory);
            $tagCollection->merge($tags);
            $modulePathName = $moduleDirectory->getRelativePathname();
            $characteristics[$modulePathName] = $tags;
        }

        return $characteristics;
    }

    private function renderTags(array $markdown): array
    {
        $tagDescriptions = new ArrayWriter($this->tagDescriptionPath);

        foreach ($this->moduleInspector->getAllTags() as $tagName) {
            $tagDescriptions->ensure($tagName);
            $markdown[$tagName] = sprintf(
                self::TEMPLATE_TAG,
                $tagName,
                $tagDescriptions->get($tagName)
            );
        }

        $tagDescriptions->dump(true);

        return $markdown;
    }
}
