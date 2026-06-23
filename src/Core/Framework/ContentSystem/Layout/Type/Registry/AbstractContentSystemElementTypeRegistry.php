<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
abstract class AbstractContentSystemElementTypeRegistry
{
    abstract public function getDecorated(): AbstractContentSystemElementTypeRegistry;

    /**
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    abstract public function all(): array;

    abstract public function has(string $name): bool;

    abstract public function get(string $name): ContentSystemElementTypeSpecification;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
