<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1623391399ChangeConstraintAclRoleAndIntegrationInApp;

/**
 * @internal
 */
#[Package('core')]
class Migration1623391399ChangeConstraintAclRoleAndIntegrationInAppTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `app`
            DROP FOREIGN KEY `fk.app.integration_id`,
            DROP FOREIGN KEY `fk.app.acl_role_id`;
        ');

        $this->connection->executeStatement('
            ALTER TABLE `app`
            ADD CONSTRAINT `fk.app.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.app.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');

        $migration = new Migration1623391399ChangeConstraintAclRoleAndIntegrationInApp();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testItChangeConstraintAclRoleAndIntegrationInApp(): void
    {
        $context = Context::createDefaultContext();

        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'id' => $integrationId,
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => 'event',
                    'url' => 'https://test.com',
                ],
            ],
        ]], $context);

        $exception = null;

        try {
            $integrationRepository = $this->getContainer()->get('integration.repository');
            $integrationRepository->delete([['id' => $integrationId]], $context);
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertInstanceOf(RestrictDeleteViolationException::class, $exception);
        static::assertNotNull($exception, 'Expected that exception will be thrown, but there was no exception.');
        static::assertSame(\count($appRepository->search(new Criteria(), $context)->getEntities()), 1);

        $exception2 = null;

        try {
            $aclRoleRepository = $this->getContainer()->get('acl_role.repository');
            $aclRoleRepository->delete([['id' => $aclRoleId]], $context);
        } catch (\Exception $e) {
            $exception2 = $e;
        }

        static::assertInstanceOf(RestrictDeleteViolationException::class, $exception2);
        static::assertNotNull($exception2, 'Expected that exception will be thrown, but there was no exception.');
        static::assertSame(\count($appRepository->search(new Criteria(), $context)->getEntities()), 1);
    }
}
