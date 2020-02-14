<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

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
