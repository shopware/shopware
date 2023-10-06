<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class FieldVisibility
{
    public static bool $isInTwigRenderingContext = false;

    /**
     * @param array<string> $internalProperties
     */
    public function __construct(private readonly array $internalProperties)
    {
    }

    public function isVisible(string $property): bool
    {
        return !static::$isInTwigRenderingContext || !\in_array($property, $this->internalProperties, true);
    }

    public function filterInvisible(array $data): array
    {
        if (!static::$isInTwigRenderingContext) {
            return $data;
        }

        return array_diff_key($data, array_flip($this->internalProperties));
    }
}
