<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1625569667NewsletterDoiForRegistered;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1625569667NewsletterDoiForRegistered
 */
class Migration1625569667NewsletterDoiForRegisteredTest extends TestCase
{
    use MigrationTestTrait;

    public function testMigration1624262862UpdateDefaultValueOnCaptchaV2(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->delete('system_config', [
            'configuration_key' => 'core.newsletter.doubleOptInRegistered',
        ]);

        $migration = new Migration1625569667NewsletterDoiForRegistered();
        $migration->update($connection);

        $configs = $connection->fetchAllAssociative(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` IN (:keys) ORDER BY `configuration_key` ASC',
            [
                'keys' => [
                    'core.newsletter.doubleOptInRegistered',
                ],
            ],
            ['keys' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEquals('{"_value": false}', $configs[0]['configuration_value']);
    }
}
