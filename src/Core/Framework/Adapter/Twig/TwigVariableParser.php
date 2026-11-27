<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ArrowFunctionExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Node\SetNode;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class TwigVariableParser
{
    /**
     * @internal
     */
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @return array<mixed>
     */
    public function parse(string $template): array
    {
        $loader = new ArrayLoader(['content.html.twig' => $template]);

        $source = $loader->getSourceContext('content.html.twig');

        $stream = $this->twig->tokenize($source);

        $parsed = $this->twig->parse($stream);

        return array_values($this->getVariables($parsed));
    }

    /**
     * @param array<Node> $nodes
     * @param array<mixed> $aliases
     *
     * @return array<mixed>
     */
    private function getVariables(iterable $nodes, array $aliases = []): array
    {
        $variables = [];
        foreach ($nodes as $node) {
            if ($node instanceof AssignNameExpression || $node instanceof ArrowFunctionExpression) {
                continue;
            }

            if ($node instanceof NameExpression) {
                $name = $node->getAttribute('name');

                if (isset($aliases[$name]) && \is_array($aliases[$name])) {
                    foreach ($aliases[$name] as $path) {
                        $variables[$path] = $path;
                    }

                    continue;
                }

                if (isset($aliases[$name])) {
                    $name = $aliases[$name];
                }

                $variables[$name] = $name;

                continue;
            }

            if ($node instanceof ConstantExpression && $nodes instanceof GetAttrExpression) {
                $value = $node->getAttribute('value');
                if (\is_string($value) && $value !== '') {
                    $variables[$value] = $value;
                }

                continue;
            }

            if ($node instanceof GetAttrExpression) {
                $root = $this->getRootNode($node);
                $rootName = $root instanceof NameExpression ? $root->getAttribute('name') : null;

                if (\is_string($rootName) && isset($aliases[$rootName]) && \is_array($aliases[$rootName])) {
                    // the root variable may resolve to several paths: resolve the access against each of them
                    foreach ($aliases[$rootName] as $aliasPath) {
                        $path = implode('.', $this->getVariables($node, [$rootName => $aliasPath] + $aliases));
                        if ($path !== '') {
                            $variables[$path] = $path;
                        }
                    }

                    continue;
                }

                $path = implode('.', $this->getVariables($node, $aliases));
                if ($path !== '') {
                    $variables[$path] = $path;
                }

                continue;
            }

            if ($node instanceof ForNode) {
                $source = $node->getNode('value_target')->getAttribute('name');

                // The sequence can be an expression (e.g. filtered or conditional) over a variable with several paths.
                $targets = $this->getVariables([$node->getNode('seq')], $aliases);

                $aliases[$source] = \count($targets) <= 1 ? (string) (reset($targets) ?: '') : array_values($targets);
            }

            if ($node instanceof SetNode) {
                $names = $node->getNode('names');
                $values = $node->getNode('values');

                $resolvedValues = $this->getVariables($values, $aliases);

                foreach ($names as $nameNode) {
                    $varName = $nameNode->getAttribute('name');

                    // Several paths (e.g. a conditional expression or a fallback) must not be glued together into
                    // one bogus association: keep each path, later accesses are resolved against every one of them.
                    if (\count($resolvedValues) <= 1) {
                        $aliases[$varName] = (string) (reset($resolvedValues) ?: '');
                    } else {
                        $aliases[$varName] = array_values($resolvedValues);
                    }
                }
            }

            if ($node instanceof Node) {
                $variables += $this->getVariables($node, $aliases);
            }
        }

        return $variables;
    }

    private function getRootNode(Node $node): Node
    {
        while ($node instanceof GetAttrExpression) {
            $node = $node->getNode('node');
        }

        return $node;
    }
}
