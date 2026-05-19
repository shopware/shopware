<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class UpdatedStates extends Struct
{
    /**
     * @param string[] $oldStates
     * @param string[] $newStates
     */
    public function __construct(
        private readonly string $id,
        private readonly array $oldStates,
        private array $newStates
    ) {
    }

    public function getId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getId', 'v6.8.0.0')
        );

        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getOldStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getOldStates', 'v6.8.0.0')
        );

        return $this->oldStates;
    }

    /**
     * @return string[]
     */
    public function getNewStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getNewStates', 'v6.8.0.0')
        );

        return $this->newStates;
    }

    /**
     * @param string[] $newStates
     */
    public function setNewStates(array $newStates): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'setNewStates', 'v6.8.0.0')
        );

        $this->newStates = $newStates;
    }
}
