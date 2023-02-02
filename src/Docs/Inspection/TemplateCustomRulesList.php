<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Symfony\Component\Finder\SplFileInfo;

class TemplateCustomRulesList
{
    private const TEMPLATE_PAGE = <<<EOD
[titleEn]: <>(Rule classes)
[hash]: <>(article:rule)

List of all rule classes across Shopware 6.

%s


EOD;
    private const TEMPLATE_BUNDLE_HEADLINE = <<<EOD
### %s

EOD;

    private const TEMPLATE_RULE = <<<EOD
[%s](https://github.com/shopware/platform/tree/master/src/Core/%s)
 : %s

EOD;

    private string $ruleDescriptionPath = __DIR__ . '/../Resources/characteristics-rule-descriptions.php';

    private string $ruleListPath = __DIR__ . '/../Resources/current/60-references-internals/10-core/60-rule-system/10-rule-list.md';

    private ModuleInspector $moduleInspector;

    public function __construct(ModuleInspector $moduleInspector)
    {
        $this->moduleInspector = $moduleInspector;
    }

    public function render(CharacteristicsCollection $tagCollection): void
    {
        $ruleCollection = $tagCollection->filterTagName(ModuleInspector::TAG_CUSTOM_RULES);
        $ruleDescriptions = new ArrayWriter($this->ruleDescriptionPath);

        $markdown = [];
        /** @var ModuleTagCollection $tags */
        foreach ($ruleCollection as $tags) {
            $bundleName = $tags->getBundleName();
            $markdown[$bundleName] = sprintf(self::TEMPLATE_BUNDLE_HEADLINE, $bundleName);

            foreach ($tags as $tag) {
                $rules = $tag->marker('rules');
                usort($rules, function (SplFileInfo $ruleA, SplFileInfo $ruleB) {
                    return strcmp($ruleA->getRelativePathname(), $ruleB->getRelativePathname());
                });
                foreach ($rules as $ruleFile) {
                    $className = $this->moduleInspector->getClassName($ruleFile);

                    $ruleDescriptions->ensure($className);

                    $markdown[] = sprintf(
                        self::TEMPLATE_RULE,
                        $className,
                        $className,
                        $ruleDescriptions->get($className)
                    );
                }
            }
        }

        $ruleDescriptions->dump(true);

        file_put_contents(
            $this->ruleListPath,
            sprintf(
                self::TEMPLATE_PAGE,
                implode(\PHP_EOL, $markdown)
            )
        );
    }
}
