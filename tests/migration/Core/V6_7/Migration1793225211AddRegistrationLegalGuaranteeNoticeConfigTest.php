<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1793225211AddRegistrationLegalGuaranteeNoticeConfig;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1793225211AddRegistrationLegalGuaranteeNoticeConfig::class)]
class Migration1793225211AddRegistrationLegalGuaranteeNoticeConfigTest extends TestCase
{
    use KernelTestBehaviour;

    private const CONFIG_KEY = 'core.loginRegistration.showLegalGuaranteeNotice';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->delete('system_config', ['configuration_key' => self::CONFIG_KEY]);
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testEnablesTheNoticeByDefault(): void
    {
        $migration = new Migration1793225211AddRegistrationLegalGuaranteeNoticeConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(['{"_value": true}'], $this->fetchConfigValues());
    }

    public function testKeepsAValueTheMerchantAlreadySaved(): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": false}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        (new Migration1793225211AddRegistrationLegalGuaranteeNoticeConfig())->update($this->connection);

        static::assertSame(['{"_value": false}'], $this->fetchConfigValues());
    }

    /**
     * @return list<string>
     */
    private function fetchConfigValues(): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            [self::CONFIG_KEY]
        );
    }
}
