<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateDeprecationNodeVisitor implements NodeVisitorInterface
{
    private ?TemplateDeprecations $deprecations = null;

    /**
     * @param list<string> $templates Templates included in the analysis, in their loader namespaces.
     */
    public function __construct(private readonly array $templates)
    {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof ModuleNode || ($source = $node->getSourceContext()) === null) {
            return $node;
        }

        $this->deprecations ??= new TemplateDeprecations($env, $this->templates);
        foreach ($this->deprecations->getDiagnostics($source->getName()) as $diagnostic) {
            // TwigStan's MetadataAnalyzer captures these at their original Twig usage locations.
            @trigger_error(\sprintf('%s in "%s" at line %d.', $diagnostic['message'], $source->getName(), $diagnostic['line']), \E_USER_DEPRECATED);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return -20;
    }
}
