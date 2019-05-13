<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

class TemplateCustomRulesList
{
    const TEMPLATE_MODULE_PAGE = <<<EOD
[titleEn]: <>(Rule List)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this. The following list provides a rough overview what domain concepts offer what kinds of interfaces.  

## Possible characteristics

%s

## Modules

%s
EOD;

    const TEMPLATE_MODULE_TAG_ITEM = '<span class="tip is--primary">%s</span>';

    const TEMPLATE_BUNDLE_HEADLINE = <<<EOD
### %s

EOD;

    const TEMPLATE_MODULE_MODULE = <<<EOD
#### %s %s

* [Sources]((https://github.com/shopware/platform/tree/master/src/Core/%s)) 

%s

EOD;

    /**
     * @var string
     */
    private $ruleDescriptionPath = __DIR__ . '/../Resources/characteristics-rule-descriptions.php';

    /**
     * @var ModuleInspector
     */
    private $moduleInspector;

    public function __construct(ModuleInspector $moduleInspector)
    {
        $this->moduleInspector = $moduleInspector;
    }

    public function render(CharacteristicsCollection $tagCollection): void
    {
        $ruleCollection = $tagCollection->filterTagName(ModuleInspector::TAG_CUSTOM_RULES);

        $markdown = [];
        /** @var ModuleTagCollection $tags */
        foreach ($ruleCollection as $tags) {
            $markdown[] = sprintf(self::TEMPLATE_BUNDLE_HEADLINE, $tags->getBundleName());
        }

        die;
        $ruleDescriptions = new ArrayWriter($this->ruleDescriptionPath);
        /** @var ModuleTag $tag */
        foreach ($tagCollection->filterName(ModuleInspector::TAG_CUSTOM_RULES) as $tag) {
            foreach ($tag->marker('rules') as $markerFile) {
                $className = $this->moduleInspector
                    ->getClassName($markerFile);

                $ruleDescriptions->ensure($className);

                var_dump($className);
            }
        }

        $ruleDescriptions->dump(true);
    }
}
