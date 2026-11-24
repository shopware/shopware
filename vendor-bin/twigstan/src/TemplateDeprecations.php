<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node as PhpNode;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FeatureFlagGuard;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Expression\Test\TrueTest;
use Twig\Node\Expression\Unary\NotUnary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ForNode;
use Twig\Node\IfNode;
use Twig\Node\IncludeNode;
use Twig\Node\MacroNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Node\TypesNode;
use Twig\Node\WithNode;

/**
 * Tracks deprecated bindings separately from inferred array shapes and runtime values.
 *
 * @internal
 *
 * @phpstan-type Declaration array{message: string, template: string, line: int}
 * @phpstan-type Context array<string, array<string, Declaration>>
 * @phpstan-type Block array{template: string, node: BlockNode}
 * @phpstan-type Blocks array<string, list<Block>>
 * @phpstan-type Frame array{blocks: Blocks, block: string|null, index: int, stack: list<string>, guards: list<array{Node, bool}>}
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
     * @var array<string, array<string, array{line: int, message: string}>>
     */
    private array $diagnostics = [];

    /**
     * @var array<string, bool>
     */
    private array $guardResults = [];

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
     * @return list<array{line: int, message: string}>
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
     */
    private function render(string $template, array $context, array $stack): void
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
        $frame = ['blocks' => $blocks, 'block' => null, 'index' => 0, 'stack' => [...$stack, ...array_keys($chain)], 'guards' => []];
        $base = array_key_last($chain);
        foreach ($chain as $owner => $module) {
            $this->walk($module->getNode('body'), $context, $owner, $frame, $owner !== $base);
        }
    }

    /**
     * @param list<string> $stack
     *
     * @return array<string, ModuleNode>
     */
    private function hierarchy(string $template, array $stack = []): array
    {
        $chain = [];
        while (($module = $this->module($template)) !== null && !isset($chain[$this->key($module)])) {
            $key = $this->key($module);
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
        foreach ($chain as $owner => $module) {
            foreach ($this->blocks($module, $owner) as $name => $definitions) {
                $blocks[$name] = [...($blocks[$name] ?? []), ...$definitions];
            }
        }

        return $blocks;
    }

    /**
     * @return Blocks
     */
    private function blocks(ModuleNode $module, string $owner): array
    {
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
            foreach ($import->getNode('blocks') as $body) {
                $block = $body->getNode('0');
                \assert($block instanceof BlockNode);
                $name = $block->getAttribute('name');
                $blocks[$aliases[$name] ?? $name] = [['template' => $this->key($import), 'node' => $block]];
            }
        }
        foreach ($module->getNode('blocks') as $body) {
            $block = $body->getNode('0');
            \assert($block instanceof BlockNode);
            $name = $this->bindingName($block);
            $blocks[$name] = [['template' => $owner, 'node' => $block], ...($blocks[$name] ?? [])];
        }

        return $blocks;
    }

    /**
     * @param Context $context
     * @param Frame $frame
     */
    private function walk(Node $node, array &$context, string $owner, array $frame, bool $skipBlocks = false): void
    {
        if ($node instanceof TypesNode) {
            foreach ($node as $type) {
                if (($declaration = $this->declaration($type, $owner)) !== null) {
                    $context[$this->bindingName($type)] = [$this->declarationKey($declaration) => $declaration];
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
                $declaration = $this->declaration($target, $owner);
                $context[$this->bindingName($target)] = $declaration === null ? [] : [$this->declarationKey($declaration) => $declaration];
            }

            return;
        }
        if ($node instanceof ContextVariable) {
            foreach ($context[$node->getAttribute('name')] ?? [] as $declaration) {
                $this->report($owner, $node, 'Variable', $node->getAttribute('name'), $declaration, $frame['guards']);
            }

            return;
        }
        if ($node instanceof IfNode) {
            $branches = [];
            $guards = $frame['guards'];
            $tests = $node->getNode('tests');
            for ($i = 0; $i < \count($tests); $i += 2) {
                $condition = $tests->getNode((string) $i);
                $branch = $context;
                $this->walk($condition, $branch, $owner, [...$frame, 'guards' => $guards]);
                $this->walk($tests->getNode((string) ($i + 1)), $branch, $owner, [...$frame, 'guards' => [...$guards, [$condition, true]]], $skipBlocks);
                $branches[] = $branch;
                $guards[] = [$condition, false];
            }
            if ($node->hasNode('else')) {
                $branch = $context;
                $this->walk($node->getNode('else'), $branch, $owner, [...$frame, 'guards' => $guards], $skipBlocks);
                $branches[] = $branch;
            } else {
                $branches[] = $context;
            }
            $context = [];
            foreach ($branches as $branch) {
                foreach ($branch as $name => $declarations) {
                    $context[$name] = ($context[$name] ?? []) + $declarations;
                }
            }

            return;
        }
        if ($node instanceof AndBinary || $node instanceof OrBinary) {
            $this->walk($node->getNode('left'), $context, $owner, $frame);
            $this->walk($node->getNode('right'), $context, $owner, [...$frame, 'guards' => [...$frame['guards'], [$node->getNode('left'), $node instanceof AndBinary]]]);

            return;
        }
        if ($node instanceof ForNode) {
            $this->walk($node->getNode('seq'), $context, $owner, $frame);
            $local = $context;
            foreach (['key_target', 'value_target'] as $target) {
                $local[$node->getNode($target)->getAttribute('name')] = [];
            }
            $local['loop'] = [];
            $this->walk($node->getNode('body'), $local, $owner, $frame);
            foreach ($context as $name => $declarations) {
                if (!\in_array($name, [$node->getNode('key_target')->getAttribute('name'), $node->getNode('value_target')->getAttribute('name'), 'loop'], true)) {
                    $context[$name] += $local[$name] ?? [];
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
                            $local[$name] = [];
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
        $key = 'block:' . $definition['template'] . ':' . $name . ':' . $index;
        if (\in_array($key, $frame['stack'], true)) {
            return;
        }
        $this->walk($definition['node']->getNode('body'), $context, $definition['template'], [...$frame, 'block' => $name, 'index' => $index, 'stack' => [...$frame['stack'], $key]]);
    }

    /** @param Context $context
     * @param Frame $frame
     */
    private function include(Node $expression, ?Node $variables, bool $withContext, Node $call, array &$context, string $owner, array $frame): void
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
                    $passed[$key] = [];
                }
            }
        }
        $inputs = [];
        foreach ($this->hierarchy($name) as $inputOwner => $inputModule) {
            $inputs += $this->inputs($inputModule->getNode('body'), $inputOwner);
        }
        foreach ($inputs as $input => $declaration) {
            if (\array_key_exists($input, $passed)) {
                $this->report($owner, $call, 'Template input', $input, $declaration, $frame['guards']);
            }
        }
        $this->render($name, $passed, $frame['stack']);
    }

    /**
     * @return array<string, Declaration>
     */
    private function inputs(Node $node, string $owner): array
    {
        $inputs = [];
        if ($node instanceof TypesNode) {
            foreach ($node as $type) {
                if (($declaration = $this->declaration($type, $owner)) !== null) {
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
    private function declaration(Node $node, string $owner): ?array
    {
        if (preg_match('/@deprecated\b([^\r\n]*)/', $node->getDocumentation() ?? '', $match) !== 1) {
            return null;
        }

        return ['message' => trim($match[1]), 'template' => $owner, 'line' => $node->getTemplateLine()];
    }

    /**
     * @param Declaration $declaration
     */
    private function declarationKey(array $declaration): string
    {
        return $declaration['template'] . ':' . $declaration['line'];
    }

    /** @param Declaration $declaration
     * @param list<array{Node, bool}> $guards
     */
    private function report(string $owner, Node $node, string $kind, string $name, array $declaration, array $guards): void
    {
        if ($kind !== 'Block override' && $this->guarded($declaration['message'], $guards)) {
            return;
        }
        $message = \sprintf('%s "%s" is deprecated: %s (declared in "%s").', $kind, $name, $declaration['message'], $declaration['template']);
        $this->diagnostics[$owner][$node->getTemplateLine() . ':' . $message] = ['line' => $node->getTemplateLine(), 'message' => $message];
    }

    /**
     * @param list<array{Node, bool}> $guards
     */
    private function guarded(string $message, array $guards): bool
    {
        if ($guards === [] || preg_match('/\btag:(v\d+\.\d+\.\d+(?:\.\d+)?)\b/', $message, $match) !== 1) {
            return false;
        }
        $version = $match[1] . (substr_count($match[1], '.') === 2 ? '.0' : '');
        if (!Feature::isMajorVersionFlag($version)) {
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
