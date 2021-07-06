<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1624884801MakeMailLinksConfigurable;

class Migration1624884801MakeMailLinksConfigurableTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testMigration1624262862UpdateDefaultValueOnCaptchaV2(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->delete('system_config', [
            'configuration_key' => 'core.newsletter.subscribeUrl',
        ]);
        $connection->delete('system_config', [
            'configuration_key' => 'core.loginRegistration.pwdRecoverUrl',
        ]);
        $connection->delete('system_config', [
            'configuration_key' => 'core.loginRegistration.confirmationUrl',
        ]);

        $migration = new Migration1624884801MakeMailLinksConfigurable();
        $migration->update($connection);

        $configs = $connection->fetchAll(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` IN (:keys) ORDER BY `configuration_key` ASC',
            [
                'keys' => [
                    'core.newsletter.subscribeUrl',
                    'core.loginRegistration.pwdRecoverUrl',
                    'core.loginRegistration.confirmationUrl',
                ],
            ],
            ['keys' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEquals('{"_value": "/registration/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%"}', $configs[0]['configuration_value']);
        static::assertEquals('{"_value": "/account/recover/password?hash=%%RECOVERHASH%%"}', $configs[1]['configuration_value']);
        static::assertEquals('{"_value": "/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%"}', $configs[2]['configuration_value']);
    }
}
