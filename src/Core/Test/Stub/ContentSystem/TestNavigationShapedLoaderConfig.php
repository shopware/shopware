<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * The config object of {@see TestNavigationShapedLoader}: an entity name and one optional defaulted property
 * reference. It carries no required property reference, which is what makes the loader never gate.
 */
#[Package('framework')]
final readonly class TestNavigationShapedLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public string $entity,
        public ?string $activeProperty = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['entity' => $this->entity];

        if ($this->activeProperty !== null) {
            $data['activeProperty'] = $this->activeProperty;
        }

        return $data;
    }
}
