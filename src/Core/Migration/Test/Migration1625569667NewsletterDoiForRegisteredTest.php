<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1625569667NewsletterDoiForRegistered;

class Migration1625569667NewsletterDoiForRegisteredTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testMigration1624262862UpdateDefaultValueOnCaptchaV2(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

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
