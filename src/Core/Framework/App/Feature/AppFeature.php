<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\Log\Package;

/**
 * A feature as declared by an app: the typed config together with the declaring app.
 *
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @template T of AppFeatureConfig
 */
#[Package('framework')]
final readonly class AppFeature
{
    /**
     * @param T $config
     */
    public function __construct(
        public string $appId,
        public string $appName,
        public bool $appActive,
        public string $appVersion,
        public bool $appHasSecret,
        public \DateTimeImmutable $createdAt,
        public AppFeatureConfig $config,
    ) {
    }
}
