<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * @internal
 */
#[Package('TODO')]
class RenderResult
{
    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public readonly string $documentContent,
        public array $extensions = [],
    ) {
    }
}
