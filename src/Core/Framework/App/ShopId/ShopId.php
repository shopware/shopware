<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type ShopIdV1Config array{value: string, app_url: string}
 * @phpstan-type ShopIdV2Config array{id: string, version: 2, fingerprints: array<string, string>, verified: bool}
 */
#[Package('framework')]
readonly class ShopId implements \Stringable
{
    /**
     * @param array<string, string> $fingerprints
     */
    private function __construct(
        public string $id,
        public array $fingerprints,
        public UrlVerificationStatus $urlVerificationStatus,
        public int $version = 2,
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function withUrlVerificationResult(bool $result): self
    {
        return static::v2(
            $this->id,
            $this->fingerprints,
            $result ? UrlVerificationStatus::newPassed() : UrlVerificationStatus::newFailed()
        );
    }

    //    public function isUrlValid(): bool
    //    {
    //        $appUrl = $this->getFingerprint(AppUrl::IDENTIFIER);
    //        if ($appUrl === null) {
    //            return false;
    //        }
    //
    //        if ($appUrl !== )
    //
    //
    //
    //    }

    public function getFingerprint(string $identifier): ?string
    {
        return $this->fingerprints[$identifier] ?? null;
    }

    public static function v1(string $id, string $appUrl): self
    {
        return new self($id, [AppUrl::IDENTIFIER => $appUrl], UrlVerificationStatus::newPending(), 1);
    }

    /**
     * @param array<string, string> $fingerprints
     */
    public static function v2(string $id, array $fingerprints, UrlVerificationStatus $urlVerificationStatus): self
    {
        return new self($id, $fingerprints, $urlVerificationStatus, 2);
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
            return self::v2(
                $config['id'],
                $config['fingerprints'],
                isset($config['url_verification_status'])
                    ? UrlVerificationStatus::fromArray($config['url_verification_status'])
                    : UrlVerificationStatus::newPending()
            );
        }

        throw AppException::invalidShopIdConfiguration();
    }

    /**
     * @return array{
     *    id: string,
     *    fingerprints: array<string, string>,
     *    url_verification_status: array{
     *        state: string,
     *        lastVerifiedAt: string|null
     *    },
     *    version: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fingerprints' => $this->fingerprints,
            'url_verification_status' => $this->urlVerificationStatus->toArray(),
            'version' => $this->version,
        ];
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
