<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemLayoutPresetRegistry
{
    abstract public function getDecorated(): AbstractContentSystemLayoutPresetRegistry;

    /**
     * @return array<string, ContentSystemLayoutPresetSpecification>
     */
    abstract public function all(): array;

    abstract public function has(string $id): bool;

    abstract public function get(string $id): ContentSystemLayoutPresetSpecification;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
