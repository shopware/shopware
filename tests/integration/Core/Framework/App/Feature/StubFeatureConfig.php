<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * @internal
 */
final class StubFeatureConfig implements AppFeatureConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
