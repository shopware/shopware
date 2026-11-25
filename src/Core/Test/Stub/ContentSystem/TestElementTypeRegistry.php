<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * A registry over a fixed specification map, for a test that needs the registry to answer for a handful of types
 * it declares itself. It answers `get()` on an unknown name the way {@see ContentSystemElementTypeRegistry} does,
 * so a lookup a test did not arrange fails as an unknown type rather than as an undefined array key.
 */
#[Package('framework')]
final class TestElementTypeRegistry extends AbstractContentSystemElementTypeRegistry
{
    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specifications
     */
    private function __construct(private readonly array $specifications)
    {
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specifications
     */
    public static function of(array $specifications): self
    {
        return new self($specifications);
    }

    public function getDecorated(): AbstractContentSystemElementTypeRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    public function all(): array
    {
        return $this->specifications;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->specifications);
    }

    public function get(string $name): ContentSystemElementTypeSpecification
    {
        return $this->specifications[$name] ?? throw ContentSystemException::elementTypeNotFound($name);
    }
}
