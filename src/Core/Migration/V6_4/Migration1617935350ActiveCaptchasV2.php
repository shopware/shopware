<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1617935350ActiveCaptchasV2 extends MigrationStep
{
    private const CONFIG_KEY = 'core.basicInformation.activeCaptchasV2';

    /**
     * @var array<string, array{name: string, isActive: bool, config?: array<string, mixed>}>
     */
    private array $captchaItems = [
        'honeypot' => [
            'name' => 'Honeypot',
            'isActive' => false,
        ],
        'basicCaptcha' => [
            'name' => 'basicCaptcha',
            'isActive' => false,
        ],
        'googleReCaptchaV2' => [
            'name' => 'googleReCaptchaV2',
            'isActive' => false,
            'config' => [
                'siteKey' => '',
                'secretKey' => '',
                'invisible' => false,
            ],
        ],
        'googleReCaptchaV3' => [
            'name' => 'googleReCaptchaV3',
            'isActive' => false,
            'config' => [
                'siteKey' => '',
                'secretKey' => '',
                'thresholdScore' => 0.5,
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1617935350;
    }

    public function update(Connection $connection): void
    {
        $configPresent = $connection->fetchOne('SELECT 1 FROM `system_config` WHERE `configuration_key` = ?', [self::CONFIG_KEY]);
        if ($configPresent !== false) {
            // Captchas are already configured, don't alter the setting
            return;
        }
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => $this->captchaItems], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
