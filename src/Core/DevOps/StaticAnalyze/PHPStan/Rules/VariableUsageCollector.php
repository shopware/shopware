<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class VariableUsageCollector extends NodeVisitorAbstract
{
    /**
     * @var array<string, int>
     */
    private array $declared = [];

    /**
     * @var string[]
     */
    private array $used = [];

    /**
     * @var array<string, true>
     */
    private array $ignore;

    /**
     * @param array<string, true> $ignore
     */
    public function __construct(array $ignore = [])
    {
        $this->ignore = array_merge(
            $ignore,
            [
                'this' => true,
                '_' => true,
                'GLOBALS' => true,
                '_SERVER' => true,
                '_GET' => true,
                '_POST' => true,
                '_FILES' => true,
                '_COOKIE' => true,
                '_SESSION' => true,
                '_REQUEST' => true,
                '_ENV' => true,
            ]
        );
    }

    public function enterNode(Node $node)
    {
        // Assignments = declarations
        if ($node instanceof Node\Expr\Assign && $node->var instanceof Node\Expr\Variable) {
            $nodeVar = $node->var;

            if (\is_string($nodeVar->name) && !\array_key_exists($nodeVar->name, $this->ignore)) {
                $this->declared[$nodeVar->name] = spl_object_id($nodeVar);
            }
        }

        // foreach key/value vars: value can be unused, but key should always be used or omitted if unused
        if ($node instanceof Node\Stmt\Foreach_) {
            if ($node->keyVar instanceof Node\Expr\Variable && \is_string($node->keyVar->name)) {
                $this->declared[$node->keyVar->name] = spl_object_id($node->keyVar);
            }
        }

        // Any variable use
        if ($node instanceof Node\Expr\Variable && \is_string($node->name)) {
            if (!\array_key_exists($node->name, $this->declared) || $this->declared[$node->name] !== spl_object_id($node)) {
                $this->used[] = $node->name;
            }
        }

        // Closure use() variables are considered used
        if ($node instanceof Node\Expr\ClosureUse && \is_string($node->var->name)) {
            $this->used[] = $node->var->name;
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getDeclared(): array
    {
        return array_unique(array_keys($this->declared));
    }

    /**
     * @return string[]
     */
    public function getUsed(): array
    {
        return array_unique($this->used);
    }
}
