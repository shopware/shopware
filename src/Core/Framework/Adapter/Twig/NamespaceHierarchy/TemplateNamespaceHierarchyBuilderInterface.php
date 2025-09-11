<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * Builds or modifies the template namespace hierarchy for Twig template resolution.
     *
     * This interface is part of a chain-of-responsibility pattern where multiple builders
     * can sequentially modify the hierarchy. Each builder receives the current state and
     * can add, remove, or reorder namespaces.
     *
     * PRIORITY SYSTEM:
     * - Lower integer values = higher priority in template resolution
     * - When sorted ascending, namespaces with lower values come first
     * - The final consumer (TemplateFinder) uses only the sorted order, not the values
     *
     * TEMPLATE RESOLUTION:
     * After all builders have run, the hierarchy determines template lookup order.
     * Templates from namespaces later in the sorted array override those from earlier ones.
     *
     * Example hierarchy structure:
     * [
     *     'Storefront' => -2,  // Highest priority (checked last, can override others)
     *     'SwagPayPal' => 0,   // Medium priority plugin
     *     'MyOwnTheme' => 1,   // Lower priority (checked first)
     * ]
     *
     * In this example, if all three provide 'header.twig':
     * - MyOwnTheme's version is checked first
     * - SwagPayPal's version overrides it if present
     * - Storefront's version has final say
     *
     * @param array<string, int> $namespaceHierarchy Current hierarchy state from previous builders
     *
     * @return array<string, int> Modified hierarchy to pass to the next builder
     */
    public function buildNamespaceHierarchy(array $namespaceHierarchy): array;
}
