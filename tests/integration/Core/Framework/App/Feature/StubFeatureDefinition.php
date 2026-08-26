<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @implements AppFeatureDefinition<AppFeatureConfig>
 */
final class StubFeatureDefinition implements AppFeatureDefinition
{
    public function getType(): string
    {
        return 'stub_feature';
    }

    public function getConfigClass(): string
    {
        return StubFeatureConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        return [];
    }

    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        if (!$declared instanceof StubFeatureConfig) {
            throw new \InvalidArgumentException('StubFeatureDefinition only handles StubFeatureConfig');
        }

        return ['name' => $declared->name, 'value' => $declared->value];
    }

    public function fromPayload(array $payload): AppFeatureConfig
    {
        return new StubFeatureConfig((string) $payload['name'], (string) $payload['value']);
    }
}
