<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;

class Migration1585490020ActivateHoneypotCaptcha extends MigrationStep
{
    private const CONFIG_KEY = 'core.basicInformation.activeCaptchas';

    public function getCreationTimestamp(): int
    {
        return 1585490020;
    }

    public function update(Connection $connection): void
    {
        $configPresent = $connection->fetchColumn('SELECT 1 FROM `system_config` WHERE `configuration_key` = ?', [self::CONFIG_KEY]);

        if ($configPresent !== false) {
            // Captchas are already configured, don't alter the setting
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => sprintf('{"_value": ["%s"]}', HoneypotCaptcha::CAPTCHA_NAME),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
