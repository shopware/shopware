<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Symfony\Component\Console\Style\SymfonyStyle;

class TemplateModuleFile
{
    private const TEMPLATE_MODULE_PAGE = <<<EOD
[titleEn]: <>(Core Module List)
[hash]: <>(article:core_modules)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this.
The following list provides a rough overview what domain concepts offer what kinds of interfaces.

## Possible characteristics

%s

## Modules

%s
EOD;

    private const TEMPLATE_MODULE_TAG_ITEM = '<span class="tip is--primary">%s</span>';

    private const TEMPLATE_MODULE_TAG = <<<EOD
<span class="tip is--primary">%s</span>
  : %s

EOD;

    private const TEMPLATE_MODULE_MODULE = <<<EOD
#### %s %s

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/%s)

%s

EOD;
    private const TEMPLATE_MODULE_BUNDLE_NAME = '### %s Bundle';

    /**
     * @var string
     */
    private $moduleDescriptionPath = __DIR__ . '/../Resources/characteristics-module-descriptions.php';

    /**
     * @var string
     */
    private $tagDescriptionPath = __DIR__ . '/../Resources/characteristics-tag-descriptions.php';

    /**
     * @var string
     */
    private $targetFile = __DIR__ . '/../Resources/current/60-references-internals/10-core/10-modules.md';

    /**
     * @var ModuleInspector
     */
    private $moduleInspector;

    public function __construct(ModuleInspector $moduleInspector)
    {
        $this->moduleInspector = $moduleInspector;
    }

    public function renderModuleList(CharacteristicsCollection $characteristics, SymfonyStyle $io): void
    {
        $renderedTags = $this->renderTags();
        $renderedModules = $this->renderCharacteristics($characteristics, $io);

        file_put_contents(
            $this->targetFile,
            sprintf(
                self::TEMPLATE_MODULE_PAGE,
                implode(\PHP_EOL, $renderedTags),
                implode(\PHP_EOL, $renderedModules)
            )
        );
    }

    protected function renderCharacteristics(CharacteristicsCollection $characteristics, SymfonyStyle $io): array
    {
        $markdown = [];

        $moduleDescriptions = new ArrayWriter($this->moduleDescriptionPath);

        /** @var ModuleTagCollection $tags */
        foreach ($characteristics as $tags) {
            $modulePathName = $tags->getModulePathName();
            [$bundleName, $moduleName] = explode('/', $modulePathName);

            $tagNames = array_map(static function (ModuleTag $tag) {
                return $tag->name();
            }, $tags->getElements());

            $renderedTags = array_map(static function (string $tagName) {
                return sprintf(self::TEMPLATE_MODULE_TAG_ITEM, $tagName);
            }, $tagNames);

            $moduleDescriptions->ensure($modulePathName);
            $markdown[$bundleName] = sprintf(self::TEMPLATE_MODULE_BUNDLE_NAME . \PHP_EOL, $bundleName);

            $markdown[$modulePathName] = sprintf(
                self::TEMPLATE_MODULE_MODULE,
                $moduleName,
                implode(' ', $renderedTags),
                $modulePathName,
                $moduleDescriptions->get($modulePathName)
            );

            $io->write(' * ' . $modulePathName . ': ' . implode(', ', $tagNames) . \PHP_EOL);
        }

        $moduleDescriptions->dump(true);

        return $markdown;
    }

    private function renderTags(): array
    {
        $tagDescriptions = new ArrayWriter($this->tagDescriptionPath);

        $markdown = [];
        foreach ($this->moduleInspector->getAllTags() as $tagName) {
            $tagDescriptions->ensure($tagName);
            $markdown[$tagName] = sprintf(
                self::TEMPLATE_MODULE_TAG,
                $tagName,
                $tagDescriptions->get($tagName)
            );
        }

        $tagDescriptions->dump(true);

        return $markdown;
    }
}
