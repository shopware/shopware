<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Storage;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('data-services')]
class GlobalStorage implements StorageInterface
{
    private const SYSTEM_CONFIG_PREFIX = 'core.consent.';

    public function __construct(private readonly SystemConfigService $configService)
    {
    }

    public static function code(): string
    {
        return 'global';
    }

    public function status(string $name, string $identifier): ConsentStateDTO
    {
        $configEntry = $this->configService->get(self::SYSTEM_CONFIG_PREFIX . $name);

        if ($configEntry === null) {
            return new ConsentStateDTO(
                name: $name,
                identifier: $identifier,
                status: ConsentState::REQUESTED // todo: is this correct?
            );
        }

        if (!isset($configEntry['status'])) {
            throw ConsentException::invalidConsentStatus();
        }

        return new ConsentStateDTO(
            name: $name,
            identifier: $identifier,
            status: ConsentState::from($configEntry['status'])
        );
    }

    public function accept(string $name, string $identifier): void
    {
        $this->configService->set(self::SYSTEM_CONFIG_PREFIX . $name, [
            'user' => $identifier,
            'status' => ConsentState::ACCEPTED->value,
            'timestamp' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function revoke(string $name, string $identifier): void
    {
        $this->configService->set(self::SYSTEM_CONFIG_PREFIX . $name, [
            'user' => $identifier,
            'status' => ConsentState::REVOKED->value,
            'timestamp' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
