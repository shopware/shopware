<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * @package core
 */
class FeatureCallSilentToken extends Node
{
    public function __construct(private readonly string $flag, Node $body, int $line, string $tag)
    {
        parent::__construct(['body' => $body], [], $line, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->raw('\Shopware\Core\Framework\Feature::callSilentIfInactive(\'' . $this->flag . '\', function () use(&$context) { ')
            ->subcompile($this->getNode('body'))
            ->raw('});');
    }
}
