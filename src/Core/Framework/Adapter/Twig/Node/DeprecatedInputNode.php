<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('framework')]
#[YieldReady]
final class DeprecatedInputNode extends Node
{
    public function __construct(
        string $path,
        string $removedIn,
        ?string $replacedBy,
        ?string $message,
        int $line,
    ) {
        parent::__construct([], [
            'path' => $path,
            'removed_in' => $removedIn,
            'replaced_by' => $replacedBy,
            'message' => $message,
        ], $line);
    }

    public function compile(Compiler $compiler): void
    {
    }
}
