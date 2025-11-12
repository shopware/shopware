<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type ShopIdV1Config array{value: string, app_url: string}
 * @phpstan-type ShopIdV2Config array{id: string, version: 2, fingerprints: array<string, string>}
 */
#[Package('framework')]
readonly class ShopId
{
    /**
     * @param array<string, string> $fingerprints
     */
    private function __construct(
        public string $id,
        public array $fingerprints = [],
        public int $version = 2,
    ) {
    }

    public function getFingerprint(string $identifier): ?string
    {
        return $this->fingerprints[$identifier] ?? null;
    }

    public static function v1(string $id, string $appUrl): self
    {
        return new self($id, [AppUrl::IDENTIFIER => $appUrl], 1);
    }

    /**
     * @param array<string, string> $fingerprints
     */
    public static function v2(string $id, array $fingerprints = []): self
    {
        return new self($id, $fingerprints, 2);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromSystemConfig(array $config): self
    {
        if (self::isV1($config)) {
            return self::v1($config['value'], $config['app_url']);
        }

        if (self::isV2($config)) {
            return self::v2($config['id'], $config['fingerprints']);
        }

        throw AppException::invalidShopIdConfiguration();
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isV1(array $config): bool
    {
        return isset($config['value'])
            && isset($config['app_url']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isV2(array $config): bool
    {
        return isset($config['id'])
            && isset($config['version'])
            && isset($config['fingerprints']);
    }
}
