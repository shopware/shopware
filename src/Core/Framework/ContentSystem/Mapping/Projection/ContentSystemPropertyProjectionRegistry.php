<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Projection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Stateless index over the tagged projections.
 *
 * Name collisions are not resolved here, they are prevented:
 * `DependencyInjection/CompilerPass/ContentSystemPropertyProjectionCompilerPass` fails the container build on a
 * duplicate name, because a name is stored on every layout referring to it and a silent winner would change
 * what those layouts render depending on service order.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemPropertyProjectionRegistry extends AbstractContentSystemPropertyProjectionRegistry
{
    /**
     * @var array<string, AbstractContentPropertyProjection>|null
     */
    private ?array $indexed = null;

    /**
     * @param iterable<AbstractContentPropertyProjection> $projections
     */
    public function __construct(private readonly iterable $projections)
    {
    }

    public function getDecorated(): AbstractContentSystemPropertyProjectionRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    public function all(): array
    {
        if ($this->indexed !== null) {
            return $this->indexed;
        }

        $indexed = [];

        foreach ($this->projections as $projection) {
            $indexed[$projection->name()] = $projection;
        }

        return $this->indexed = $indexed;
    }

    public function get(string $name): ?AbstractContentPropertyProjection
    {
        return $this->all()[$name] ?? null;
    }
}
