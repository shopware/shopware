<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

interface TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * Gets the current hierarchy as param and can extend and modify the hierarchy.
     * Needs to return the new hierarchy.
     * Example hierarchy structure:
     * [
     *     'Storefront',
     *     'SwagPayPal',
     *     'MyOwnTheme',
     * ]
     *
     * @param string[] $namespaceHierarchy
     *
     * @return string[]
     */
    public function buildNamespaceHierarchy(array $namespaceHierarchy): array;
}
