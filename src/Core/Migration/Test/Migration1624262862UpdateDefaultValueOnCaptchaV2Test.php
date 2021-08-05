<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1624262862UpdateDefaultValueOnCaptchaV2;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;

class Migration1624262862UpdateDefaultValueOnCaptchaV2Test extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const CONFIG_KEY = 'core.basicInformation.activeCaptchasV2';

    private Connection $connection;

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

    public function testMigration1624262862UpdateDefaultValueOnCaptchaV2(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchas', [BasicCaptcha::CAPTCHA_NAME]);
        $connection = $this->getContainer()->get(Connection::class);

        $connection->update('system_config', [
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => \json_encode(['_value' => $this->captchaItems]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ], [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration = new Migration1624262862UpdateDefaultValueOnCaptchaV2();
        $migration->update($connection);

        $activeCaptchasV2 = $connection->fetchColumn('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $activeCaptchasV2 = json_decode($activeCaptchasV2, true);

        static::assertTrue($activeCaptchasV2['_value'][BasicCaptcha::CAPTCHA_NAME]['isActive']);
    }
}
