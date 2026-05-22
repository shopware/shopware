<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\System\CustomEntity\CustomEntityCollection;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
class CustomEntityLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    private AppFixture $appFixture;

    /**
     * @var EntityRepository<CustomEntityCollection>
     */
    private EntityRepository $customEntityRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customEntityRepository = static::getContainer()->get('custom_entity.repository');

        $appFixture = static::getContainer()->get(AppFixture::class);
        \assert($appFixture instanceof AppFixture);
        $this->appFixture = $appFixture;
    }

    public function testRemoveAppSoftDeletesCustomEntitiesWhenKeepingUserData(): void
    {
        $this->stopTransactionAfter();

        $manifest = $this->appFixture->loadManifest(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->createLifecycleService($app)->updateApp($app);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $app->getId()));

        $customEntities = $this->customEntityRepository->search($criteria, $this->context);

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        $this->createLifecycleService($app)->removeApp($app, $this->context, true);

        $customEntities = $this->customEntityRepository->search(new Criteria([$customEntity->getId()]), $this->context);

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);
        static::assertNotNull($customEntity->getDeletedAt());

        $this->connection->executeStatement('DELETE FROM custom_entity');
        $this->connection->executeStatement('DELETE FROM app WHERE name ="customEntities"');
        $this->connection->executeStatement('DELETE FROM integration WHERE label ="customEntities"');
        $this->connection->executeStatement('DELETE FROM acl_role WHERE name ="customEntities"');
        $this->connection->executeStatement('DROP TABLE `custom_entity_test`');

        $this->startTransactionBefore();
    }

    public function testRemoveAppHardDeletesCustomEntities(): void
    {
        $this->stopTransactionAfter();

        $manifest = $this->appFixture->loadManifest(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->createLifecycleService($app)->updateApp($app);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $app->getId()));

        $customEntities = $this->customEntityRepository->search($criteria, $this->context);

        static::assertTrue(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(1, $customEntities);

        $customEntity = $customEntities->first();
        static::assertNotNull($customEntity);

        $this->createLifecycleService($app)->removeApp($app, $this->context, false);

        $customEntities = $this->customEntityRepository->search(new Criteria([$customEntity->getId()]), $this->context);

        static::assertFalse(TableHelper::tableExists($this->connection, 'custom_entity_test'));
        static::assertCount(0, $customEntities);

        $this->connection->executeStatement('DELETE FROM custom_entity');
        $this->connection->executeStatement('DELETE FROM app WHERE name ="customEntities"');
        $this->connection->executeStatement('DELETE FROM integration WHERE label ="customEntities"');
        $this->connection->executeStatement('DELETE FROM acl_role WHERE name ="customEntities"');

        $this->startTransactionBefore();
    }

    private function createLifecycleService(AppEntity $app): CustomEntityLifecycleService
    {
        return new CustomEntityLifecycleService(
            static::getContainer()->get(CustomEntityPersister::class),
            static::getContainer()->get(CustomEntitySchemaUpdater::class),
            static::getContainer()->get(CustomEntityEnrichmentService::class),
            static::getContainer()->get(CustomEntityXmlSchemaValidator::class),
            new StaticSourceResolver([
                $app->getName() => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/default/app'),
            ]),
            $this->connection,
            $this->customEntityRepository,
        );
    }
}
