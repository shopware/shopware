<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedBlockReferenceNode;
use Shopware\Core\Framework\Adapter\Twig\Node\RemoveDeprecatedBlocksNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedBlockNodeVisitor implements NodeVisitorInterface
{
    /**
     * @var list<array<string, string>>
     */
    private array $blocks = [];

    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof ModuleNode) {
            return $node;
        }

        $blocks = [];
        foreach ($node->getNode('blocks') as $body) {
            foreach ($body as $block) {
                if (!$block instanceof BlockNode || !str_contains($block->getDocumentation() ?? '', '@deprecated')) {
                    continue;
                }

                if (preg_match_all('/@deprecated\s+tag:(\S+)/', $block->getDocumentation(), $matches) !== 1
                    || preg_match('/^v\d+\.\d+\.0$/', $matches[1][0]) !== 1) {
                    throw AdapterException::invalidDeprecatedBlockDocumentation($block->getTemplateLine(), $block->getSourceContext());
                }

                $blocks[$block->getAttribute('name')] = $matches[1][0] . '.0';
            }
        }

        $this->blocks[] = $blocks;
        if ($blocks !== []) {
            $node->setNode('constructor_end', new Nodes([
                $node->getNode('constructor_end'),
                new RemoveDeprecatedBlocksNode($blocks),
            ]));
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            array_pop($this->blocks);

            return $node;
        }

        $index = array_key_last($this->blocks);
        $blocks = $index === null ? [] : $this->blocks[$index];
        if (!$node instanceof BlockReferenceNode || !isset($blocks[$node->getAttribute('name')])) {
            return $node;
        }

        return new DeprecatedBlockReferenceNode($node->getAttribute('name'), $blocks[$node->getAttribute('name')], $node->getTemplateLine());
    }

    public function getPriority(): int
    {
        return -10;
    }
}
