<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1624262862UpdateDefaultValueOnCaptchaV2;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1624262862UpdateDefaultValueOnCaptchaV2
 */
class Migration1624262862UpdateDefaultValueOnCaptchaV2Test extends TestCase
{
    use MigrationTestTrait;

    private const CONFIG_KEY = 'core.basicInformation.activeCaptchasV2';

    /**
     * @see \Shopware\Storefront\Framework\Captcha\BasicCaptcha::CAPTCHA_NAME
     */
    private const CAPTCHA_NAME = 'basicCaptcha';

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

    public function testMigration1624262862UpdateDefaultValueOnCaptchaV2(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->update('system_config', [
            'configuration_key' => 'core.basicInformation.activeCaptchas',
            'configuration_value' => \json_encode(['_value' => [self::CAPTCHA_NAME]]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ], [
            'configuration_key' => 'core.basicInformation.activeCaptchas',
        ]);

        $connection->update('system_config', [
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => \json_encode(['_value' => $this->captchaItems], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ], [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration = new Migration1624262862UpdateDefaultValueOnCaptchaV2();
        $migration->update($connection);

        $activeCaptchasV2 = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $activeCaptchasV2 = json_decode((string) $activeCaptchasV2, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($activeCaptchasV2['_value'][self::CAPTCHA_NAME]['isActive']);
    }
}
