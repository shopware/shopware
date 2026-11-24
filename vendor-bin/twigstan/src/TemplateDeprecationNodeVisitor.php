<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 *
 * @phpstan-import-type Diagnostic from TemplateDeprecations
 */
#[Package('framework')]
final class TemplateDeprecationNodeVisitor implements NodeVisitorInterface
{
    private ?TemplateDeprecations $deprecations = null;

    /**
     * @var array<string, true>
     */
    private array $reported = [];

    /**
     * @var array<string, array<int, list<Diagnostic>>>
     */
    private array $diagnostics = [];

    /**
     * @param list<string> $templates Templates included in the analysis, in their loader namespaces.
     */
    public function __construct(private readonly array $templates)
    {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (($source = $node->getSourceContext()) === null) {
            return $node;
        }
        if ($node instanceof ModuleNode) {
            $this->deprecations ??= new TemplateDeprecations($env, $this->templates);
            $this->reported = [];
            if (!isset($this->diagnostics[$source->getName()])) {
                $this->diagnostics[$source->getName()] = [];
                foreach ($this->deprecations->getDiagnostics($source->getName()) as $diagnostic) {
                    $this->diagnostics[$source->getName()][$diagnostic['line']][] = $diagnostic;
                }
            }

            return $node;
        }
        $kind = match (true) {
            $node instanceof BlockNode => 'Block override',
            $node instanceof ContextVariable => 'Variable',
            $node instanceof IncludeNode, $node instanceof FunctionExpression && $node->getAttribute('name') === 'include' => 'Template input',
            $node instanceof BlockReferenceExpression => 'Block call',
            default => null,
        };
        if ($kind === null || $this->deprecations === null) {
            return $node;
        }
        foreach ($this->diagnostics[$source->getName()][$node->getTemplateLine()] ?? [] as $diagnostic) {
            if ($diagnostic['kind'] !== $kind || $diagnostic['line'] !== $node->getTemplateLine()
                || ($node instanceof ContextVariable || $node instanceof BlockNode) && $diagnostic['name'] !== $node->getAttribute('name')
            ) {
                continue;
            }
            $key = $source->getName() . ':' . $diagnostic['line'] . ':' . $diagnostic['message'];
            if (isset($this->reported[$key])) {
                continue;
            }
            $this->reported[$key] = true;
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
