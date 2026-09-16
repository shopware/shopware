<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\Exception\DeprecatedInputSyntaxError;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputExpression;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\BodyNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Test\DefinedTest;
use Twig\Node\ForNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SetNode;
use Twig\Node\WithNode;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Parser;
use Twig\Template;

/**
 * @internal
 *
 * @phpstan-type Descriptor array{path: string, removed_in: string, replaced_by: ?string, message: ?string, template: string, line: int}
 */
#[Package('framework')]
final class DeprecatedInputNodeVisitor implements NodeVisitorInterface
{
    /**
     * @var list<array{descriptors: array<string, Descriptor>, template: string}>
     */
    private array $frames = [];

    /**
     * @var list<array<string, true>>
     */
    private array $localScopes = [];

    /**
     * @var list<Node>
     */
    private array $parents = [];

    /**
     * @var \WeakMap<Node, true>
     */
    private \WeakMap $ignoredNodes;

    /**
     * @var array<string, true>
     */
    private array $resolving = [];

    public function __construct()
    {
        $this->ignoredNodes = new \WeakMap();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->frames[] = [
                'descriptors' => $this->collectDescriptors($node, $env),
                'template' => $node->getSourceContext()?->getName() ?? 'unknown template',
            ];
            $this->localScopes[] = [];
        } elseif ($node instanceof BlockNode) {
            $this->localScopes[] = [];
        }

        if ($node instanceof DefinedTest || $node instanceof MacroNode) {
            $this->ignore($node);
        }

        if ($node instanceof ForNode) {
            $locals = $this->assignedNames($node->getNode('key_target')) + $this->assignedNames($node->getNode('value_target'));
            $this->ignoreLocalReads($node->getNode('body'), $locals);
        }

        if ($node instanceof WithNode) {
            if ($node->getAttribute('only')) {
                $this->ignore($node->getNode('body'));
            } elseif ($node->hasNode('variables') && $node->getNode('variables') instanceof ArrayExpression) {
                $locals = [];
                foreach ($node->getNode('variables')->getKeyValuePairs() as $pair) {
                    if ($pair['key'] instanceof ConstantExpression && \is_string($pair['key']->getAttribute('value'))) {
                        $locals[$pair['key']->getAttribute('value')] = true;
                    }
                }

                $this->ignoreLocalReads($node->getNode('body'), $locals);
            }
        }

        $this->parents[] = $node;

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        \array_pop($this->parents);

        if ($node instanceof ModuleNode) {
            \array_pop($this->frames);
            \array_pop($this->localScopes);

            return $node;
        }

        if ($node instanceof BlockNode) {
            \array_pop($this->localScopes);

            return $node;
        }

        if ($node instanceof SetNode && $this->localScopes !== [] && $this->isDirectScopeStatement()) {
            $index = \array_key_last($this->localScopes);
            $this->localScopes[$index] += $this->assignedNames($node->getNode('names'));

            return $node;
        }

        if (!$node instanceof AbstractExpression
            || $node instanceof AssignNameExpression
            || $node instanceof DeprecatedInputExpression
            || isset($this->ignoredNodes[$node])
            || $this->frames === []
        ) {
            return $node;
        }

        $path = $this->resolvePath($node);
        if ($path === null) {
            return $node;
        }

        $frame = $this->frames[\array_key_last($this->frames)];
        $rootName = \strstr($path, '.', true) ?: $path;
        foreach ($this->localScopes as $locals) {
            if (isset($locals[$rootName])) {
                return $node;
            }
        }

        $descriptor = $frame['descriptors'][$path] ?? null;
        if ($descriptor === null) {
            return $node;
        }

        $message = $descriptor['message'] ?? \sprintf('Use "%s" instead.', $descriptor['replaced_by']);
        $message = \sprintf(
            'Twig input "%s" declared in "%s" at line %d is deprecated and will be removed with feature "%s". %s Accessed in "%s" at line %d.',
            $descriptor['path'],
            $descriptor['template'],
            $descriptor['line'],
            $descriptor['removed_in'],
            $message,
            $frame['template'],
            $node->getTemplateLine(),
        );

        return new DeprecatedInputExpression($node, $descriptor['removed_in'], $message);
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return array<string, Descriptor>
     */
    private function collectDescriptors(ModuleNode $module, Environment $env): array
    {
        $descriptors = $this->collectInheritedDescriptors($module, $env);
        $declarations = [];
        $this->findAllDeclarations($module, $declarations);

        foreach ($declarations as $declaration) {
            $path = $declaration->getAttribute('path');
            if (!\is_string($path)) {
                continue;
            }

            if (isset($descriptors[$path])) {
                DeprecatedInputSyntaxError::raise(\sprintf('The input "%s" has more than one deprecation declaration.', $path), $declaration->getTemplateLine(), $module->getSourceContext());
            }

            $removedIn = $declaration->getAttribute('removed_in');
            $replacedBy = $declaration->getAttribute('replaced_by');
            $message = $declaration->getAttribute('message');
            $descriptors[$path] = [
                'path' => $path,
                'removed_in' => \is_string($removedIn) ? $removedIn : '',
                'replaced_by' => \is_string($replacedBy) ? $replacedBy : null,
                'message' => \is_string($message) ? $message : null,
                'template' => $module->getSourceContext()?->getName() ?? 'unknown template',
                'line' => $declaration->getTemplateLine(),
            ];
        }

        $module->setAttribute('shopware_deprecated_inputs', $descriptors);

        return $descriptors;
    }

    /**
     * @return array<string, Descriptor>
     */
    private function collectInheritedDescriptors(ModuleNode $module, Environment $env): array
    {
        if (!$module->hasNode('parent')) {
            return [];
        }

        $parent = $module->getNode('parent');
        if (!$parent instanceof ConstantExpression || !\is_string($parent->getAttribute('value'))) {
            return [];
        }

        $name = $parent->getAttribute('value');
        if (isset($this->resolving[$name])) {
            return [];
        }

        $this->resolving[$name] = true;
        try {
            $source = $env->getLoader()->getSourceContext($name);
            $parentModule = (new Parser($env))->parse($env->tokenize($source));
        } finally {
            unset($this->resolving[$name]);
        }

        $descriptors = $parentModule->getAttribute('shopware_deprecated_inputs');

        return \is_array($descriptors) ? $descriptors : [];
    }

    /**
     * @param array<int, DeprecatedInputNode> $declarations
     */
    private function findAllDeclarations(Node $node, array &$declarations): void
    {
        if ($node instanceof DeprecatedInputNode) {
            $declarations[\spl_object_id($node)] = $node;

            return;
        }

        foreach ($node as $child) {
            $this->findAllDeclarations($child, $declarations);
        }
    }

    private function ignore(Node $node): void
    {
        $this->ignoredNodes[$node] = true;
        foreach ($node as $child) {
            $this->ignore($child);
        }
    }

    /**
     * @param array<string, true> $locals
     */
    private function ignoreLocalReads(Node $node, array $locals): void
    {
        if ($node instanceof AbstractExpression) {
            $path = $this->resolvePath($node);
            $rootName = $path === null ? null : (\strstr($path, '.', true) ?: $path);
            if ($rootName !== null && isset($locals[$rootName])) {
                $this->ignoredNodes[$node] = true;
            }
        }

        foreach ($node as $child) {
            $this->ignoreLocalReads($child, $locals);
        }
    }

    /**
     * @return array<string, true>
     */
    private function assignedNames(Node $node): array
    {
        $names = [];
        if ($node instanceof AssignNameExpression && \is_string($node->getAttribute('name'))) {
            $names[$node->getAttribute('name')] = true;
        }

        foreach ($node as $child) {
            $names += $this->assignedNames($child);
        }

        return $names;
    }

    private function isDirectScopeStatement(): bool
    {
        for ($index = \count($this->parents) - 1; $index >= 0; --$index) {
            $parent = $this->parents[$index];
            if ($parent instanceof BlockNode || $parent instanceof ModuleNode) {
                return true;
            }

            if (!$parent instanceof BodyNode && !$parent instanceof Nodes && $parent::class !== Node::class) {
                return false;
            }
        }

        return false;
    }

    private function resolvePath(AbstractExpression $expression): ?string
    {
        if ($expression instanceof DeprecatedInputExpression) {
            $inner = $expression->getNode('expression');

            return $inner instanceof AbstractExpression ? $this->resolvePath($inner) : null;
        }

        if ($expression instanceof NameExpression && !$expression instanceof AssignNameExpression) {
            $name = $expression->getAttribute('name');

            return \is_string($name) ? $name : null;
        }

        if (!$expression instanceof GetAttrExpression || $expression->getAttribute('type') === Template::METHOD_CALL) {
            return null;
        }

        $attribute = $expression->getNode('attribute');
        $root = $expression->getNode('node');
        if (!$attribute instanceof ConstantExpression || !$root instanceof AbstractExpression) {
            return null;
        }

        $attribute = $attribute->getAttribute('value');
        $root = $this->resolvePath($root);
        if (!\is_string($attribute) || $root === null) {
            return null;
        }

        return $root . '.' . $attribute;
    }
}
