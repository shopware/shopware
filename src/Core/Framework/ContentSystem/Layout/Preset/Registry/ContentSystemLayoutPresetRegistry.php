<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\AbstractContentSystemLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemLayoutPresetRegistry extends AbstractContentSystemLayoutPresetRegistry
{
    /**
     * @param iterable<AbstractContentSystemLayoutPresetLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function getDecorated(): AbstractContentSystemLayoutPresetRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, ContentSystemLayoutPresetSpecification>
     */
    public function all(): array
    {
        $presets = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $preset) {
                if (\array_key_exists($preset->id, $presets)) {
                    throw ContentSystemException::layoutPresetDuplicate($preset->id);
                }

                $presets[$preset->id] = $preset;
            }
        }

        return $presets;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->all());
    }

    public function get(string $id): ContentSystemLayoutPresetSpecification
    {
        return $this->all()[$id] ?? throw ContentSystemException::layoutPresetNotFound($id);
    }
}
