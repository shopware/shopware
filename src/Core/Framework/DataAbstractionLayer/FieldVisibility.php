<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * @package core
 *
 * @internal
 */
class FieldVisibility
{
    public static bool $isInTwigRenderingContext = false;

    /**
     * @var array<string>
     */
    private array $internalProperties;

    /**
     * @param array<string> $internalProperties
     */
    public function __construct(array $internalProperties)
    {
        $this->internalProperties = $internalProperties;
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
