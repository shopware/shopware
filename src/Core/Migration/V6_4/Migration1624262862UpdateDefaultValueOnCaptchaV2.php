<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1624262862UpdateDefaultValueOnCaptchaV2 extends MigrationStep
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
        return 1624262862;
    }

    public function update(Connection $connection): void
    {
        $configId = $connection->fetchOne('SELECT id FROM system_config WHERE configuration_key = :key AND updated_at IS NULL', [
            'key' => self::CONFIG_KEY,
        ]);

        if (!$configId) {
            return;
        }

        $this->migrationDataFromActiveCaptchaV1($connection);
        $connection->update('system_config', [
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => $this->captchaItems], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => $configId,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function migrationDataFromActiveCaptchaV1(Connection $connection): void
    {
        $configActiveCaptchaV1 = 'core.basicInformation.activeCaptchas';
        $activeCaptchas = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ?', [$configActiveCaptchaV1]);
        $activeCaptchas = json_decode((string) $activeCaptchas, true, 512, \JSON_THROW_ON_ERROR);
        foreach ($activeCaptchas['_value'] as $value) {
            $this->captchaItems[(string) $value]['isActive'] = true;
        }
    }
}
