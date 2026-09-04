<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Registry;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemBindingSpecificationRegistry
{
    abstract public function getDecorated(): AbstractContentSystemBindingSpecificationRegistry;

    /**
     * Keyed by source-qualified id ("source:id"), unique by construction.
     *
     * @return array<string, BindingSpecification>
     */
    abstract public function all(): array;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return list<BindingSpecification>
     */
    final public function byType(string $type): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (BindingSpecification $specification) => $specification->type() === $type,
        ));
    }

    final public function get(string $qualifiedId): ?BindingSpecification
    {
        return $this->all()[$qualifiedId] ?? null;
    }
}
