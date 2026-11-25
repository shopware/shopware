<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node as PhpNode;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FeatureFlagGuard;
use Shopware\Core\Framework\Adapter\Twig\Node\ReturnNode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\BodyNode;
use Twig\Node\EmbedNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ForNode;
use Twig\Node\IfNode;
use Twig\Node\IncludeNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SetNode;
use Twig\Node\TypesNode;
use Twig\Node\WithNode;

/**
 * Tracks deprecated bindings separately from inferred array shapes and runtime values.
 *
 * @internal
 *
 * @phpstan-type Proof array{inactive: list<string>, conditions: array<string, bool>}
 * @phpstan-type Declaration array{message: string, template: string, line: int, proofs: list<Proof>}
 * @phpstan-type Diagnostic array{line: int, message: string, kind: string, name: string}
 * @phpstan-type Binding array{declarations: array<string, Declaration>, proofs: list<Proof>}
 * @phpstan-type Context array<string, Binding>
 * @phpstan-type Block array{template: string, node: BlockNode, module: ModuleNode}
 * @phpstan-type Blocks array<string, list<Block>>
 * @phpstan-type Frame array{blocks: Blocks, block: string|null, index: int, stack: list<string>, guards: list<array{Node, bool}>, module: ModuleNode}
 */
#[Package('framework')]
final class TemplateDeprecations
{
    private readonly Environment $parser;

    /**
     * @var array<string, ModuleNode|null>
     */
    private array $modules = [];

    /**
     * @var array<string, array<string, Diagnostic>>
     */
    private array $diagnostics = [];

    /**
     * @var array<string, bool>
     */
    private array $guardResults = [];

    /**
     * @var array<string, list<string>>
     */
    private array $inactiveResults = [];

    /**
     * @var array<string, list<Proof>>
     */
    private array $proofResults = [];

    /**
     * @param list<string> $templates
     */
    public function __construct(Environment $twig, array $templates)
    {
        // Parse source without analysis visitors, which replace nodes and discard their documentation.
        $this->parser = new Environment($twig->getLoader(), ['optimizations' => 0, 'autoescape' => false]);
        foreach ($twig->getTokenParsers() as $tokenParser) {
            $this->parser->addTokenParser(clone $tokenParser);
        }
        foreach ($twig->getFunctions() as $function) {
            $this->parser->addFunction($function);
        }
        foreach ($twig->getFilters() as $filter) {
            $this->parser->addFilter($filter);
        }
        foreach ($twig->getTests() as $test) {
            $this->parser->addTest($test);
        }
        $this->parser->getExpressionParsers()->add(iterator_to_array($twig->getExpressionParsers()));
        // Index every entrypoint before emitting diagnostics: include contexts can originate in later files.
        foreach ($templates as $template) {
            $this->render($template, [], []);
        }
    }

    /**
     * @return list<Diagnostic>
     */
    public function getDiagnostics(string $template): array
    {
        $module = $this->module($template);
        if ($module === null) {
            return [];
        }
        $diagnostics = array_values($this->diagnostics[$this->key($module)] ?? []);
        usort($diagnostics, static fn (array $a, array $b): int => [$a['line'], $a['message']] <=> [$b['line'], $b['message']]);

        return $diagnostics;
    }

    private function module(string $template): ?ModuleNode
    {
        if (\array_key_exists($template, $this->modules)) {
            return $this->modules[$template];
        }
        try {
            return $this->modules[$template] = $this->parser->parse($this->parser->tokenize($this->parser->getLoader()->getSourceContext($template)));
        } catch (LoaderError|SyntaxError) {
            // TwigStan reports unresolved templates and syntax errors itself.
            return $this->modules[$template] = null;
        }
    }

    private function key(ModuleNode $module): string
    {
        $source = $module->getSourceContext();
        \assert($source !== null);

        return $source->getPath() !== '' ? $source->getPath() : $source->getName();
    }

    /**
     * @param Context $context
     * @param list<string> $stack
     * @param list<array{Node, bool}> $guards
     */
    private function render(string|ModuleNode $template, array $context, array $stack, array $guards = []): void
    {
        $chain = $this->hierarchy($template, $stack);
        if ($chain === []) {
            return;
        }
        $blocks = $this->inheritedBlocks($chain);
        foreach ($blocks as $name => $definitions) {
            foreach ($definitions as $index => $definition) {
                foreach (\array_slice($definitions, $index + 1) as $ancestor) {
                    if (($declaration = $this->declaration($ancestor['node'], $ancestor['template'])) !== null) {
                        $this->report($definition['template'], $definition['node'], 'Block override', $name, $declaration, []);
                        break;
                    }
                }
            }
        }
        $frame = ['blocks' => $blocks, 'block' => null, 'index' => 0, 'stack' => [...$stack, ...array_keys($chain)], 'guards' => $guards, 'module' => reset($chain)];
        $base = array_key_last($chain);
        foreach ($chain as $identity => $module) {
            $this->walk($module->getNode('body'), $context, $this->key($module), [...$frame, 'module' => $module], $identity !== $base);
        }
    }

    /**
     * @param list<string> $stack
     *
     * @return array<string, ModuleNode>
     */
    private function hierarchy(string|ModuleNode $template, array $stack = []): array
    {
        $chain = [];
        while (($module = $template instanceof ModuleNode ? $template : $this->module($template)) !== null) {
            $index = $module->getAttribute('index');
            $key = $this->key($module) . ($index === null ? '' : '#embed:' . $index);
            if (isset($chain[$key])) {
                break;
            }
            if (\in_array($key, $stack, true)) {
                return [];
            }
            $chain[$key] = $module;
            $parent = $module->hasNode('parent') ? $this->literal($module->getNode('parent')) : null;
            if ($parent === null) {
                break;
            }
            $template = $parent;
        }

        return $chain;
    }

    /**
     * @param array<string, ModuleNode> $chain
     *
     * @return Blocks
     */
    private function inheritedBlocks(array $chain): array
    {
        $blocks = [];
        foreach ($chain as $module) {
            foreach ($this->blocks($module, $this->key($module)) as $name => $definitions) {
                $blocks[$name] = [...($blocks[$name] ?? []), ...$definitions];
            }
        }

        return $blocks;
    }

    /**
     * @param list<string> $visited
     *
     * @return Blocks
     */
    private function blocks(ModuleNode $module, string $owner, array $visited = []): array
    {
        if (\in_array($owner, $visited, true)) {
            return [];
        }
        $visited[] = $owner;
        $blocks = [];
        foreach ($module->getNode('traits') as $trait) {
            $name = $this->literal($trait->getNode('template'));
            if ($name === null || ($import = $this->module($name)) === null) {
                continue;
            }
            $aliases = [];
            foreach ($trait->getNode('targets') as $name => $target) {
                $aliases[$name] = $target->getAttribute('value');
            }
            foreach ($this->blocks($import, $this->key($import), $visited) as $name => $definitions) {
                $blocks[$aliases[$name] ?? $name] = $definitions;
            }
        }
        foreach ($module->getNode('blocks') as $body) {
            $block = $body->getNode('0');
            \assert($block instanceof BlockNode);
            $name = $this->bindingName($block);
            $blocks[$name] = [['template' => $owner, 'node' => $block, 'module' => $module], ...($blocks[$name] ?? [])];
        }

        return $blocks;
    }

    /**
     * @param Context $context
     * @param Frame $frame
     */
    private function walk(Node $node, array &$context, string $owner, array $frame, bool $skipBlocks = false): void
    {
        if ($this->proofs($frame['guards']) === []) {
            return;
        }
        if ($node instanceof TypesNode) {
            foreach ($node as $type) {
                if (($declaration = $this->bindingDeclaration($type, $node, $owner)) !== null) {
                    $name = $this->bindingName($type);
                    $context[$name] = $this->binding($declaration, $context[$name]['proofs'] ?? $this->proofs([]));
                } else {
                    $context[$this->bindingName($type)] ??= $this->binding(null, $this->proofs([]));
                }
            }

            return;
        }
        if ($node instanceof SetNode) {
            $this->walk($node->getNode('values'), $context, $owner, $frame);
            foreach ($node->getNode('names') as $target) {
                if (!$target instanceof ContextVariable) {
                    continue;
                }
                $declaration = $this->bindingDeclaration($target, $node, $owner);
                $context[$this->bindingName($target)] = $this->binding($declaration, $this->proofs($frame['guards']));
            }

            return;
        }
        if ($node instanceof ContextVariable) {
            foreach ($context[$node->getAttribute('name')]['declarations'] ?? [] as $declaration) {
                $this->report($owner, $node, 'Variable', $node->getAttribute('name'), $declaration, $frame['guards'], $declaration['proofs']);
            }

            return;
        }
        if ($node instanceof IfNode) {
            $branches = [];
            $guards = $frame['guards'];
            $tests = $node->getNode('tests');
            for ($i = 0; $i < \count($tests); $i += 2) {
                $condition = $tests->getNode((string) $i);
                $branchGuards = [...$guards, [$condition, true]];
                $branch = $this->inBranch($context, $branchGuards);
                $this->walk($condition, $context, $owner, [...$frame, 'guards' => $guards]);
                $body = $tests->getNode((string) ($i + 1));
                $this->walk($body, $branch, $owner, [...$frame, 'guards' => $branchGuards], $skipBlocks);
                if (!$this->terminates($body)) {
                    $branches[] = $branch;
                }
                $guards[] = [$condition, false];
            }
            if ($node->hasNode('else')) {
                $branch = $this->inBranch($context, $guards);
                $this->walk($node->getNode('else'), $branch, $owner, [...$frame, 'guards' => $guards], $skipBlocks);
                if (!$this->terminates($node->getNode('else'))) {
                    $branches[] = $branch;
                }
            } else {
                $branches[] = $this->inBranch($context, $guards);
            }
            $context = [];
            foreach ($branches as $branch) {
                foreach ($branch as $name => $binding) {
                    $context[$name] = isset($context[$name]) ? $this->mergeBindings($context[$name], $binding) : $binding;
                }
            }

            return;
        }
        if ($node instanceof AndBinary || $node instanceof OrBinary) {
            $this->walk($node->getNode('left'), $context, $owner, $frame);
            $this->walk($node->getNode('right'), $context, $owner, [...$frame, 'guards' => [...$frame['guards'], [$node->getNode('left'), $node instanceof AndBinary]]]);

            return;
        }
        if ($node instanceof ConditionalTernary) {
            $condition = $node->getNode('test');
            $this->walk($condition, $context, $owner, $frame);
            foreach (['left' => true, 'right' => false] as $side => $truthy) {
                $guards = [...$frame['guards'], [$condition, $truthy]];
                $branch = $this->inBranch($context, $guards);
                $this->walk($node->getNode($side), $branch, $owner, [...$frame, 'guards' => $guards]);
            }

            return;
        }
        if ($node instanceof ForNode) {
            $this->walk($node->getNode('seq'), $context, $owner, $frame);
            $local = $context;
            foreach (['key_target', 'value_target'] as $target) {
                $local[$node->getNode($target)->getAttribute('name')] = $this->binding(null, $this->proofs($frame['guards']));
            }
            $local['loop'] = $this->binding(null, $this->proofs($frame['guards']));
            $this->walk($node->getNode('body'), $local, $owner, $frame);
            foreach ($context as $name => $binding) {
                if (!\in_array($name, [$node->getNode('key_target')->getAttribute('name'), $node->getNode('value_target')->getAttribute('name'), 'loop'], true)) {
                    $context[$name] = $this->mergeBindings($binding, $local[$name] ?? $binding);
                }
            }
            if ($node->hasNode('else')) {
                $this->walk($node->getNode('else'), $context, $owner, $frame);
            }

            return;
        }
        if ($node instanceof BlockReferenceNode) {
            if (!$skipBlocks) {
                $this->block($node->getAttribute('name'), 0, $context, $frame);
            }

            return;
        }
        if ($node instanceof ParentExpression) {
            if ($frame['block'] !== null) {
                $this->block($frame['block'], $frame['index'] + 1, $context, $frame);
            }

            return;
        }
        if ($node instanceof BlockReferenceExpression && ($name = $this->literal($node->getNode('name'))) !== null) {
            $target = $frame;
            if ($node->hasNode('template')) {
                $expression = $node->getNode('template');
                $this->walk($expression, $context, $owner, $frame);
                $template = $this->literal($expression);
                if ($template === null) {
                    return;
                }
                $target['blocks'] = $this->inheritedBlocks($this->hierarchy($template));
            }
            foreach ($target['blocks'][$name] ?? [] as $definition) {
                if (($declaration = $this->declaration($definition['node'], $definition['template'])) !== null) {
                    $this->report($owner, $node, 'Block call', $name, $declaration, $frame['guards']);
                    break;
                }
            }
            $this->block($name, 0, $context, $target);

            return;
        }
        if ($node instanceof EmbedNode) {
            foreach ($frame['module']->getAttribute('embedded_templates') as $embedded) {
                if ($embedded instanceof ModuleNode && $embedded->getAttribute('index') === $node->getAttribute('index') && $embedded->hasNode('parent')) {
                    $this->include($embedded->getNode('parent'), $node->hasNode('variables') ? $node->getNode('variables') : null, !$node->getAttribute('only'), $node, $context, $owner, $frame, $embedded);
                    break;
                }
            }

            return;
        }
        if ($node instanceof IncludeNode) {
            $this->include($node->getNode('expr'), $node->hasNode('variables') ? $node->getNode('variables') : null, !$node->getAttribute('only'), $node, $context, $owner, $frame);

            return;
        }
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'include') {
            $arguments = $node->getNode('arguments');
            $template = $this->argument($arguments, '0', 'template');
            if ($template !== null) {
                $with = $this->argument($arguments, '2', 'with_context');
                $withContext = !$with instanceof ConstantExpression || $with->getAttribute('value') !== false;
                $this->include($template, $this->argument($arguments, '1', 'variables'), $withContext, $node, $context, $owner, $frame);
            }

            return;
        }
        if ($node instanceof WithNode) {
            $local = $node->getAttribute('only') ? [] : $context;
            if ($node->hasNode('variables')) {
                $variables = $node->getNode('variables');
                $this->walk($variables, $context, $owner, $frame);
                if ($variables instanceof ArrayExpression) {
                    foreach ($variables->getKeyValuePairs() as $pair) {
                        if (($name = $this->literal($pair['key'])) !== null) {
                            $local[$name] = $this->binding(null, $this->proofs($frame['guards']));
                        }
                    }
                }
            }
            $this->walk($node->getNode('body'), $local, $owner, $frame);

            return;
        }
        if ($node instanceof MacroNode || $node instanceof BlockNode) {
            return;
        }
        foreach ($node as $child) {
            $this->walk($child, $context, $owner, $frame, $skipBlocks);
            if ($this->terminates($child)) {
                break;
            }
        }
    }

    /** @param Context $context
     * @param Frame $frame
     */
    private function block(string $name, int $index, array $context, array $frame): void
    {
        $definition = $frame['blocks'][$name][$index] ?? null;
        if ($definition === null) {
            return;
        }
        $key = 'block:' . spl_object_id($definition['node']) . ':' . $name . ':' . $index;
        if (\in_array($key, $frame['stack'], true)) {
            return;
        }
        $this->walk($definition['node']->getNode('body'), $context, $definition['template'], [...$frame, 'block' => $name, 'index' => $index, 'stack' => [...$frame['stack'], $key], 'module' => $definition['module']]);
    }

    /** @param Context $context
     * @param Frame $frame
     */
    private function include(Node $expression, ?Node $variables, bool $withContext, Node $call, array &$context, string $owner, array $frame, ?ModuleNode $embedded = null): void
    {
        $this->walk($expression, $context, $owner, $frame);
        if ($variables !== null) {
            $this->walk($variables, $context, $owner, $frame);
        }
        $name = $this->literal($expression);
        if ($name === null || ($module = $this->module($name)) === null) {
            return;
        }
        $passed = $withContext ? $context : [];
        if ($variables instanceof ArrayExpression) {
            foreach ($variables->getKeyValuePairs() as $pair) {
                if (($key = $this->literal($pair['key'])) !== null) {
                    $passed[$key] = $this->binding(null, $this->proofs($frame['guards']));
                }
            }
        }
        $inputs = [];
        foreach ($this->hierarchy($name) as $inputModule) {
            $inputs += $this->inputs($inputModule->getNode('body'), $this->key($inputModule));
        }
        foreach ($inputs as $input => $declaration) {
            if (\array_key_exists($input, $passed)) {
                $this->report($owner, $call, 'Template input', $input, $declaration, $frame['guards'], $passed[$input]['proofs']);
            }
        }
        $this->render($embedded ?? $name, $passed, $frame['stack'], $frame['guards']);
    }

    /**
     * @return array<string, Declaration>
     */
    private function inputs(Node $node, string $owner): array
    {
        $inputs = [];
        if ($node instanceof TypesNode) {
            foreach ($node as $type) {
                if (($declaration = $this->bindingDeclaration($type, $node, $owner)) !== null) {
                    $inputs[$type->getAttribute('name')] = $declaration;
                }
            }
        } elseif (!$node instanceof MacroNode) {
            foreach ($node as $child) {
                $inputs += $this->inputs($child, $owner);
            }
        }

        return $inputs;
    }

    private function literal(Node $node): ?string
    {
        return $node instanceof ConstantExpression && \is_string($node->getAttribute('value')) ? $node->getAttribute('value') : null;
    }

    private function bindingName(Node $node): string
    {
        $name = $node->getAttribute('name');
        \assert(\is_string($name));

        return $name;
    }

    private function argument(Node $arguments, string $position, string $name): ?Node
    {
        return $arguments->hasNode($name) ? $arguments->getNode($name) : ($arguments->hasNode($position) ? $arguments->getNode($position) : null);
    }

    /**
     * @return Declaration|null
     */
    private function bindingDeclaration(Node $binding, TypesNode|SetNode $node, string $owner): ?array
    {
        $bindings = $node instanceof SetNode ? $node->getNode('names') : $node;

        return $this->declaration($binding, $owner)
            ?? (\count($bindings) === 1 ? $this->declaration($node, $owner) : null);
    }

    /**
     * @return Declaration|null
     */
    private function declaration(Node $node, string $owner): ?array
    {
        if (preg_match('/@deprecated\b([^\r\n]*)/', $node->getDocumentation() ?? '', $match) !== 1) {
            return null;
        }

        return ['message' => trim($match[1]), 'template' => $owner, 'line' => $node->getTemplateLine(), 'proofs' => $this->proofs([])];
    }

    /**
     * @param Declaration $declaration
     */
    private function declarationKey(array $declaration): string
    {
        return $declaration['template'] . ':' . $declaration['line'];
    }

    /**
     * @param Declaration|null $declaration
     * @param list<Proof> $proofs
     *
     * @return Binding
     */
    private function binding(?array $declaration, array $proofs): array
    {
        if ($declaration !== null) {
            $declaration['proofs'] = $proofs;
        }

        return ['declarations' => $declaration === null ? [] : [$this->declarationKey($declaration) => $declaration], 'proofs' => $proofs];
    }

    /**
     * @param Context $context
     * @param list<array{Node, bool}> $guards
     *
     * @return Context
     */
    private function inBranch(array $context, array $guards): array
    {
        $proof = $this->proofs($guards)[0] ?? null;
        if ($proof === null) {
            return [];
        }
        if ($proof['inactive'] === [] && $proof['conditions'] === []) {
            return $context;
        }
        foreach ($context as $name => $binding) {
            $binding['proofs'] = $this->refineProofs($binding['proofs'], $proof);
            foreach ($binding['declarations'] as $key => $declaration) {
                $declaration['proofs'] = $this->refineProofs($declaration['proofs'], $proof);
                $binding['declarations'][$key] = $declaration;
            }
            $context[$name] = $binding;
        }

        return $context;
    }

    /**
     * @param Binding $left
     * @param Binding $right
     *
     * @return Binding
     */
    private function mergeBindings(array $left, array $right): array
    {
        $left['proofs'] = $this->mergeProofs([...$left['proofs'], ...$right['proofs']]);
        foreach ($right['declarations'] as $key => $declaration) {
            if (isset($left['declarations'][$key])) {
                $declaration['proofs'] = $this->mergeProofs([...$left['declarations'][$key]['proofs'], ...$declaration['proofs']]);
            }
            $left['declarations'][$key] = $declaration;
        }

        return $left;
    }

    /**
     * @param list<Proof> $proofs
     * @param Proof $guard
     *
     * @return list<Proof>
     */
    private function refineProofs(array $proofs, array $guard): array
    {
        $refined = [];
        foreach ($proofs as $proof) {
            foreach ($guard['conditions'] as $flag => $truthy) {
                if (isset($proof['conditions'][$flag]) && $proof['conditions'][$flag] !== $truthy) {
                    continue 2;
                }
            }
            $refined[] = ['inactive' => array_values(array_unique([...$proof['inactive'], ...$guard['inactive']])), 'conditions' => $proof['conditions'] + $guard['conditions']];
        }

        return $this->mergeProofs($refined);
    }

    /**
     * @param list<Proof> $proofs
     *
     * @return list<Proof>
     */
    private function mergeProofs(array $proofs): array
    {
        $merged = [];
        foreach ($proofs as $proof) {
            sort($proof['inactive']);
            $key = implode(',', $proof['inactive']);
            if (isset($merged[$key])) {
                // Keep one conservative alternative per version proof, rather than multiplying template paths.
                foreach ($merged[$key]['conditions'] as $flag => $truthy) {
                    if (!isset($proof['conditions'][$flag]) || $proof['conditions'][$flag] !== $truthy) {
                        unset($merged[$key]['conditions'][$flag]);
                    }
                }
            } else {
                $merged[$key] = $proof;
            }
        }

        return array_values($merged);
    }

    /**
     * @param list<array{Node, bool}> $guards
     *
     * @return list<Proof>
     */
    private function proofs(array $guards): array
    {
        $key = implode(',', array_map(static fn (array $guard): string => spl_object_id($guard[0]) . ':' . (int) $guard[1], $guards));
        if (isset($this->proofResults[$key])) {
            return $this->proofResults[$key];
        }
        $conditions = [];
        foreach ($guards as [$condition, $truthy]) {
            foreach ($this->featureConditions($condition, $truthy) as $flag => $value) {
                if (isset($conditions[$flag]) && $conditions[$flag] !== $value) {
                    return $this->proofResults[$key] = [];
                }
                $conditions[$flag] = $value;
            }
        }

        return $this->proofResults[$key] = [['inactive' => $this->inactiveFlags($guards), 'conditions' => $conditions]];
    }

    /**
     * @return array<string, bool>
     */
    private function featureConditions(Node $node, bool $truthy): array
    {
        if ($node instanceof TrueTest) {
            return $this->featureConditions($node->getNode('node'), $truthy);
        }
        if ($node instanceof NotUnary) {
            return $this->featureConditions($node->getNode('node'), !$truthy);
        }
        if (($node instanceof AndBinary && $truthy) || ($node instanceof OrBinary && !$truthy)) {
            return $this->featureConditions($node->getNode('left'), $truthy) + $this->featureConditions($node->getNode('right'), $truthy);
        }
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'feature') {
            $arguments = $node->getNode('arguments');
            if ($arguments->hasNode('0') && ($flag = $this->literal($arguments->getNode('0'))) !== null) {
                return [Feature::normalizeName($flag) => $truthy];
            }
        }

        return [];
    }

    private function terminates(Node $node): bool
    {
        if ($node instanceof ReturnNode) {
            return true;
        }
        if ($node instanceof IfNode) {
            if (!$node->hasNode('else') || !$this->terminates($node->getNode('else'))) {
                return false;
            }
            $tests = $node->getNode('tests');
            for ($i = 1; $i < \count($tests); $i += 2) {
                if (!$this->terminates($tests->getNode((string) $i))) {
                    return false;
                }
            }

            return true;
        }
        if ($node instanceof Nodes || $node instanceof BodyNode) {
            foreach ($node as $child) {
                if ($this->terminates($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array{Node, bool}> $guards
     *
     * @return list<string>
     */
    private function inactiveFlags(array $guards): array
    {
        $key = implode(',', array_map(static fn (array $guard): string => spl_object_id($guard[0]) . ':' . (int) $guard[1], $guards));
        if (isset($this->inactiveResults[$key])) {
            return $this->inactiveResults[$key];
        }
        $expressions = array_map(fn (array $guard): PhpNode\Expr => $this->condition($guard[0]), $guards);
        $inactive = [];
        foreach ((new NodeFinder())->findInstanceOf($expressions, PhpNode\Expr\StaticCall::class) as $call) {
            $flag = $call->getArgs()[0]->value ?? null;
            if (!$flag instanceof PhpNode\Scalar\String_ || !Feature::isMajorVersionFlag($flag->value)) {
                continue;
            }
            $version = $flag->value . (substr_count($flag->value, '.') === 2 ? '.0' : '');
            if ($this->guarded('tag:' . $version, $guards)) {
                $inactive[] = $version;
            }
        }

        return $this->inactiveResults[$key] = array_values(array_unique($inactive));
    }

    /** @param Declaration $declaration
     * @param list<array{Node, bool}> $guards
     * @param list<Proof>|null $proofs
     */
    private function report(string $owner, Node $node, string $kind, string $name, array $declaration, array $guards, ?array $proofs = null): void
    {
        if ($proofs !== null) {
            $guard = $this->proofs($guards)[0] ?? null;
            if ($guard === null) {
                return;
            }
            $proofs = $this->refineProofs($proofs, $guard);
            if ($proofs === []) {
                return;
            }
        }
        $inactive = $proofs === null ? [] : array_values(array_intersect(...array_column($proofs, 'inactive')));
        if ($kind !== 'Block override' && $this->guarded($declaration['message'], $guards, $inactive)) {
            return;
        }
        $message = \sprintf('%s "%s" is deprecated: %s (declared in "%s").', $kind, $name, $declaration['message'], $declaration['template']);
        $this->diagnostics[$owner][$node->getTemplateLine() . ':' . $message] = ['line' => $node->getTemplateLine(), 'message' => $message, 'kind' => $kind, 'name' => $name];
    }

    /**
     * @param list<array{Node, bool}> $guards
     * @param list<string> $inactive
     */
    private function guarded(string $message, array $guards, array $inactive = []): bool
    {
        if (preg_match('/\btag:(v\d+\.\d+\.\d+(?:\.\d+)?)\b/', $message, $match) !== 1) {
            return false;
        }
        $version = $match[1] . (substr_count($match[1], '.') === 2 ? '.0' : '');
        if (!Feature::isMajorVersionFlag($version)) {
            return false;
        }
        if (\in_array($version, $inactive, true)) {
            return true;
        }
        if ($guards === []) {
            return false;
        }
        $code = 'deprecated_usage();';
        $printer = new Standard();
        foreach (array_reverse($guards) as [$condition, $truthy]) {
            $expression = $this->condition($condition);
            $code = 'if (' . $printer->prettyPrintExpr($truthy ? $expression : new PhpNode\Expr\BooleanNot($expression)) . ') {' . $code . '}';
        }
        $cacheKey = $version . ':' . $code;
        if (isset($this->guardResults[$cacheKey])) {
            return $this->guardResults[$cacheKey];
        }
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function analysis() {' . $code . '}');
        \assert($nodes !== null);
        $target = (new NodeFinder())->findFirst($nodes, static fn (PhpNode $node): bool => $node instanceof PhpNode\Expr\FuncCall && $node->name instanceof PhpNode\Name && $node->name->toString() === 'deprecated_usage');
        \assert($target !== null);

        return $this->guardResults[$cacheKey] = (new FeatureFlagGuard($target, Feature::normalizeName($version)))->matches($nodes);
    }

    private function condition(Node $node): PhpNode\Expr
    {
        if ($node instanceof TrueTest) {
            return $this->condition($node->getNode('node'));
        }
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'feature') {
            $arguments = $node->getNode('arguments');
            if ($arguments->hasNode('0') && ($flag = $this->literal($arguments->getNode('0'))) !== null) {
                return new PhpNode\Expr\StaticCall(new PhpNode\Name\FullyQualified(Feature::class), 'isActive', [new PhpNode\Arg(new PhpNode\Scalar\String_($flag))]);
            }
        }
        if ($node instanceof NotUnary) {
            return new PhpNode\Expr\BooleanNot($this->condition($node->getNode('node')));
        }
        if ($node instanceof AndBinary || $node instanceof OrBinary) {
            $class = $node instanceof AndBinary ? PhpNode\Expr\BinaryOp\BooleanAnd::class : PhpNode\Expr\BinaryOp\BooleanOr::class;

            return new $class($this->condition($node->getNode('left')), $this->condition($node->getNode('right')));
        }

        return new PhpNode\Expr\FuncCall(new PhpNode\Name('unknown_condition'));
    }
}
