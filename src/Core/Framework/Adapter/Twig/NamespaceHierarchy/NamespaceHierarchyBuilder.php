<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\HandlerChain;

/**
 * @HandlerChain(
 *     serviceTag="shopware.twig.hierarchy_builder",
 *     handlerInterface="TemplateNamespaceHierarchyBuilderInterface"
 * )
 */
class NamespaceHierarchyBuilder
{
    /**
     * @var TemplateNamespaceHierarchyBuilderInterface[]
     */
    private $namespaceHierarchyBuilders;

    public function __construct(iterable $namespaceHierarchyBuilders)
    {
        $this->namespaceHierarchyBuilders = $namespaceHierarchyBuilders;
    }

    public function buildHierarchy(): array
    {
        $hierarchy = [];

        foreach ($this->namespaceHierarchyBuilders as $hierarchyBuilder) {
            $hierarchy = $hierarchyBuilder->buildNamespaceHierarchy($hierarchy);
        }

        return $hierarchy;
    }
}
