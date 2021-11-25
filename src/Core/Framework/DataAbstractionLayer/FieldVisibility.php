<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * @internal
 */
class FieldVisibility
{
    public static bool $isInTwigRenderingContext = false;

    /**
     * @var string[]
     */
    private array $internalProperties;

    /**
     * @param string[] $internalProperties
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
