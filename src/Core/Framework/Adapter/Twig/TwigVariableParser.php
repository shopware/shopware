<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class TwigVariableParser
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function parse(string $template): array
    {
        $loader = new ArrayLoader(['content.html.twig' => $template]);

        $source = $loader->getSourceContext('content.html.twig');

        $stream = $this->twig->tokenize($source);

        $parsed = $this->twig->parse($stream);

        return array_values($this->getVariables($parsed));
    }

    private function getVariables(iterable $nodes): array
    {
        $variables = [];
        foreach ($nodes as $node) {
            if ($node instanceof AssignNameExpression) {
                continue;
            }

            if ($node instanceof NameExpression) {
                $name = $node->getAttribute('name');
                $variables[$name] = $name;

                continue;
            }

            if ($node instanceof ConstantExpression && $nodes instanceof GetAttrExpression) {
                $value = $node->getAttribute('value');
                if (!empty($value) && \is_string($value)) {
                    $variables[$value] = $value;
                }

                continue;
            }

            if ($node instanceof GetAttrExpression) {
                $path = implode('.', $this->getVariables($node));
                if (!empty($path)) {
                    $variables[$path] = $path;
                }

                continue;
            }

            if ($node instanceof Node) {
                $variables += $this->getVariables($node);

                continue;
            }
        }

        return $variables;
    }
}
