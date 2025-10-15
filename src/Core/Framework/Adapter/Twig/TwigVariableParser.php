<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\EmptyNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Node\TextNode;

#[Package('framework')]
class TwigVariableParser
{
    /**
     * @internal
     */
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @return string[]
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
     * @param Node|Node[]|array<int, Node> $nodes
     * @param array<string, string> $aliases
     *
     * @return array<string,string>
     */
    private function getVariables(iterable $nodes, array $aliases = []): array
    {
        $variables = [];

        foreach ($nodes as $node) {
            if ($node instanceof EmptyNode || $node instanceof TextNode) {
                continue;
            }

            if ($node instanceof NameExpression || $nodes instanceof AssignContextVariable) {
                $name = $node->getAttribute('name');
                $variables[$name] = $aliases[$name] ?? $name;
                continue;
            }

            if ($node instanceof ConstantExpression && $nodes instanceof GetAttrExpression) {
                $value = $node->getAttribute('value');
                $variables[$value] = $value;
                continue;
            }

            if ($node instanceof ConstantExpression && $nodes instanceof FilterExpression) {
                $value = $node->getAttribute('value');
                if ($value === 'first' || $value === 'last') {
                    $variables[$value] = $value;
                }
                continue;
            }

            if ($node instanceof GetAttrExpression) {
                $path = implode('.', $this->getVariables($node, $aliases));
                if (!empty($path)) {
                    $variables[$path] = $path;
                }
                continue;
            }

            if ($node instanceof SetNode) {
                $names = $node->getNode('names');
                $values = $node->getNode('values');

                for ($i = 0; $names->hasNode((string) $i); $i++) {
                    $alias = implode('.', $this->getVariables([$names->getNode((string) $i)], $aliases));
                    $valueNode = $values->getNode((string) $i);

                    if ($valueNode instanceof GetAttrExpression || $valueNode instanceof NameExpression) {
                        $aliases[$alias] = implode('.', $this->getVariables([$valueNode], $aliases));
                    } else {
                        $variables = array_merge($variables, $this->getVariables($valueNode, $aliases));
                        $aliases[$alias] = '';
                    }
                }
                continue;
            }

            if ($node instanceof ForNode) {
                $path = implode('.', $this->getVariables([$node->getNode('seq')], $aliases));
                $loopAliases = [...$aliases];
                $loopAliases[$node->getNode('value_target')->getAttribute('name')] = $path . '.0';
                $variables = array_merge($variables, $this->getVariables([$node->getNode('body')], $loopAliases));
                continue;
            }

            if ($node instanceof Node) {
                $variables = array_merge($variables, $this->getVariables($node, $aliases));
            }
        }

        return array_filter($variables, fn (string $variable) => $variable !== '' && !\str_starts_with($variable, '.'));
    }
}
