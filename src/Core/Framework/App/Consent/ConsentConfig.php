<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Consent;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @phpstan-type ConsentPayload array{name: string, scope: string, revision?: string|null}
 */
#[Package('framework')]
readonly class ConsentConfig implements AppFeatureConfig
{
    public function __construct(
        public string $name,
        public string $scope,
        public ?string $revision,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return ConsentPayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'scope' => $this->scope,
            'revision' => $this->revision,
        ];
    }
}
