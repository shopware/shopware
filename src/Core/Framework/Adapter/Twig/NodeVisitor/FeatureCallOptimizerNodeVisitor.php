<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\IfNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\TextNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class FeatureCallOptimizerNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof IfNode) {
            return $node;
        }

        $tests = $node->getNode('tests');
        /** @var array<string, Node> $optimizedTests */
        $optimizedTests = [];

        for ($i = 0, $count = \count($tests); $i < $count; $i += 2) {
            $test = $tests->getNode((string) $i);
            $body = $tests->hasNode((string) ($i + 1)) ? $tests->getNode((string) ($i + 1)) : $this->createEmptyNode($node);
            $optimizedTest = $this->optimizeExpression($test);

            if ($optimizedTest === false) {
                continue;
            }

            if ($optimizedTest === true) {
                if ($optimizedTests === []) {
                    return $body;
                }

                $node->setNode('tests', new Nodes($optimizedTests, $tests->getTemplateLine()));
                $node->setNode('else', $body);

                return $node;
            }

            $optimizedTests[(string) \count($optimizedTests)] = $optimizedTest ?? $test;
            $optimizedTests[(string) \count($optimizedTests)] = $body;
        }

        if ($optimizedTests === []) {
            return $node->hasNode('else') ? $node->getNode('else') : $this->createEmptyNode($node);
        }

        $node->setNode('tests', new Nodes($optimizedTests, $tests->getTemplateLine()));

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function optimizeExpression(Node $node): Node|bool|null
    {
        if ($node instanceof TrueTest) {
            return $this->optimizeExpression($node->getNode('node'));
        }

        if ($node instanceof NotUnary) {
            $inner = $this->optimizeExpression($node->getNode('node'));

            if (\is_bool($inner)) {
                return !$inner;
            }

            if ($inner instanceof Node) {
                $node->setNode('node', $inner);

                return $node;
            }

            return null;
        }

        if ($node instanceof AndBinary) {
            return $this->optimizeBinaryExpression($node, false);
        }

        if ($node instanceof OrBinary) {
            return $this->optimizeBinaryExpression($node, true);
        }

        return $this->resolveFeatureCall($node);
    }

    private function optimizeBinaryExpression(AndBinary|OrBinary $node, bool $shortCircuitValue): Node|bool|null
    {
        $leftNode = $node->getNode('left');
        $rightNode = $node->getNode('right');

        $left = $this->optimizeExpression($leftNode);
        $right = $this->optimizeExpression($rightNode);

        if ($left === $shortCircuitValue || $right === $shortCircuitValue) {
            return $shortCircuitValue;
        }

        if (\is_bool($left)) {
            return $right ?? $rightNode;
        }

        if (\is_bool($right)) {
            return $left ?? $leftNode;
        }

        if (!$left instanceof Node && !$right instanceof Node) {
            return null;
        }

        if ($left instanceof Node) {
            $node->setNode('left', $left);
        }

        if ($right instanceof Node) {
            $node->setNode('right', $right);
        }

        return $node;
    }

    private function resolveFeatureCall(Node $node): ?bool
    {
        if (!$node instanceof FunctionExpression || $node->getAttribute('name') !== 'feature') {
            return null;
        }

        $arguments = $node->getNode('arguments');
        if (!$arguments->hasNode('0')) {
            return null;
        }

        $featureName = $arguments->getNode('0');
        if (!$featureName instanceof ConstantExpression) {
            return null;
        }

        $featureName = $featureName->getAttribute('value');
        if (!\is_string($featureName) || !Feature::has($featureName)) {
            return null;
        }

        return Feature::isActive($featureName);
    }

    private function createEmptyNode(Node $node): TextNode
    {
        return new TextNode('', $node->getTemplateLine());
    }
}
