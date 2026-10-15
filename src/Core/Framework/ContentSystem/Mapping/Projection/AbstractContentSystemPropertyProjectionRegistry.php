<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Projection;

use Shopware\Core\Framework\Log\Package;

/**
 * Single authority over the registered projections, read by the write boundary that admits a stored mapping,
 * the render path that applies one, and the introspection endpoint that lists them.
 *
 * Lookup only. Deciding what to do about a name nothing answers to is the caller's call, and the two callers
 * answer it differently on purpose: the write boundary rejects, the render path falls back.
 */
#[Package('framework')]
abstract class AbstractContentSystemPropertyProjectionRegistry
{
    abstract public function getDecorated(): self;

    /**
     * @return array<string, AbstractContentPropertyProjection> keyed by name, in registration order
     */
    abstract public function all(): array;

    abstract public function get(string $name): ?AbstractContentPropertyProjection;
}
