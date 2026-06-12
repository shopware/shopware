<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityCollection;
use Shopware\Core\System\CustomEntity\CustomEntityEntity;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(CustomEntityLifecycleService::class)]
class CustomEntityLifecycleServiceTest extends TestCase
{
    public function testResultIsNullIfThereIsNoExtension(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects($this->never())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->never())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            new StaticSourceResolver([
                'SwagExampleTest' => new StaticFilesystem(),
            ]),
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
        );

        static::assertNull(
            $customEntityLifecycleService->updateApp(AppFixture::createAppEntity('SwagExampleTest', 'test'))
        );
    }

    public function testUpdateAppOnlyCustomEntities(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects($this->once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            new StaticSourceResolver([
                'SwagExampleTest' => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntities/app'),
            ]),
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
        );

        $app = AppFixture::createAppEntity('SwagExampleTest', 'test');

        $schema = $customEntityLifecycleService->updateApp($app);

        static::assertInstanceOf(CustomEntityXmlSchema::class, $schema);

        $this->checkFieldsAndFlagsCount($schema);
    }

    public function testUpdateAppCustomEntitiesWithAdminUi(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects($this->once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            new StaticSourceResolver([
                'SwagExampleTest' => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntitiesAndAdminUis/app'),
            ]),
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
        );

        $app = AppFixture::createAppEntity('SwagExampleTest', 'test');

        $schema = $customEntityLifecycleService->updateApp($app);
        static::assertInstanceOf(CustomEntityXmlSchema::class, $schema);

        $this->checkFieldsAndFlagsCount($schema, true);
    }

    public function testAllowsDisablingWithoutCustomEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertTrue($customEntityLifecycleService->allowsDisabling($app));
    }

    public function testAllowsDisablingWithNonRestrictingAssociations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['onDelete' => AssociationField::CASCADE],
                    ['onDelete' => AssociationField::SET_NULL],
                    [],
                ], \JSON_THROW_ON_ERROR),
            ]);

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertTrue($customEntityLifecycleService->allowsDisabling($app));
    }

    public function testDisallowsDisablingWithRestrictingAssociation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['onDelete' => AssociationField::RESTRICT],
                ], \JSON_THROW_ON_ERROR),
            ]);

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertFalse($customEntityLifecycleService->allowsDisabling($app));
    }

    public function testCanRemoveAppDataWithoutCustomEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);
        $connection->expects($this->never())->method('fetchOne');

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertTrue($customEntityLifecycleService->canRemoveAppData($app));
    }

    public function testCanRemoveAppDataWhenRestrictingCustomEntityTableIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'custom_entity_test' => json_encode([
                    ['onDelete' => AssociationField::RESTRICT],
                ], \JSON_THROW_ON_ERROR),
            ]);
        $connection
            ->expects($this->once())
            ->method('quoteSingleIdentifier')
            ->with('custom_entity_test')
            ->willReturn('`custom_entity_test`');
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM `custom_entity_test`')
            ->willReturn(0);

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertTrue($customEntityLifecycleService->canRemoveAppData($app));
    }

    public function testCannotRemoveAppDataWhenRestrictingCustomEntityTableHasRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'custom_entity_test' => json_encode([
                    ['onDelete' => AssociationField::RESTRICT],
                ], \JSON_THROW_ON_ERROR),
            ]);
        $connection
            ->expects($this->once())
            ->method('quoteSingleIdentifier')
            ->with('custom_entity_test')
            ->willReturn('`custom_entity_test`');
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM `custom_entity_test`')
            ->willReturn(1);

        $customEntityLifecycleService = $this->createLifecycleService($connection);

        $app = AppFixture::createAppEntity();

        static::assertFalse($customEntityLifecycleService->canRemoveAppData($app));
    }

    public function testRemoveAppDoesNothingWithoutCustomEntities(): void
    {
        $context = Context::createDefaultContext();
        $customEntityRepository = $this->createCustomEntityRepository();

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->never())->method('update');

        $customEntityLifecycleService = $this->createLifecycleService(
            $this->createMock(Connection::class),
            $customEntityRepository,
            $customEntitySchemaUpdater
        );

        $customEntityLifecycleService->removeApp(AppFixture::createAppEntity(), $context, true);

        static::assertSame([], $customEntityRepository->updates);
        static::assertSame([], $customEntityRepository->deletes);
    }

    public function testRemoveAppSoftDeletesCustomEntitiesWhenKeepingUserData(): void
    {
        $context = Context::createDefaultContext();
        $customEntity = (new CustomEntityEntity())->assign(['id' => Uuid::randomHex()]);
        $customEntityRepository = $this->createCustomEntityRepository($customEntity);

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->never())->method('update');

        $customEntityLifecycleService = $this->createLifecycleService(
            $this->createMock(Connection::class),
            $customEntityRepository,
            $customEntitySchemaUpdater
        );

        $customEntityLifecycleService->removeApp(AppFixture::createAppEntity(), $context, true);

        static::assertCount(1, $customEntityRepository->updates);
        static::assertSame($customEntity->getId(), $customEntityRepository->updates[0][0]['id']);
        static::assertNull($customEntityRepository->updates[0][0]['appId']);
        static::assertInstanceOf(\DateTimeImmutable::class, $customEntityRepository->updates[0][0]['deletedAt']);
        static::assertSame([], $customEntityRepository->deletes);
    }

    public function testRemoveAppHardDeletesCustomEntities(): void
    {
        $context = Context::createDefaultContext();
        $customEntity = (new CustomEntityEntity())->assign(['id' => Uuid::randomHex()]);
        $customEntityRepository = $this->createCustomEntityRepository($customEntity);

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects($this->once())->method('update');

        $customEntityLifecycleService = $this->createLifecycleService(
            $this->createMock(Connection::class),
            $customEntityRepository,
            $customEntitySchemaUpdater
        );

        $customEntityLifecycleService->removeApp(AppFixture::createAppEntity(), $context, false);

        static::assertSame([], $customEntityRepository->updates);
        static::assertSame([[['id' => $customEntity->getId()]]], $customEntityRepository->deletes);
    }

    private function checkFieldsAndFlagsCount(CustomEntityXmlSchema $customEntityXmlSchema, bool $withAdminUi = false): void
    {
        $entities = $customEntityXmlSchema->getEntities();
        static::assertNotNull($entities);

        $entities = $entities->getEntities();
        static::assertCount(3, $entities);

        $ceSuperSimple = $this->getSpecificCustomEntity($entities, 'ce_super_simple');
        static::assertCount(1, $ceSuperSimple->getFields());
        static::assertCount($withAdminUi ? 1 : 0, $ceSuperSimple->getFlags());

        // @todo NEXT-22697 - Re-implement, when re-enabling cms-aware
        //        $ceCmsAware = $this->getSpecificCustomEntity($entities, 'ce_cms_aware');
        //        static::assertCount(15, $ceCmsAware->getFields());
        //        static::assertCount(1 + ($withAdminUi ? 1 : 0), $ceCmsAware->getFlags());

        $ceComplex = $this->getSpecificCustomEntity($entities, 'ce_complex');
        static::assertCount(22, $ceComplex->getFields());
        static::assertCount(0, $ceComplex->getFlags());
    }

    /**
     * @param list<Entity> $customEntities
     */
    private function getSpecificCustomEntity(array $customEntities, string $ceName): Entity
    {
        return \array_values(
            \array_filter(
                $customEntities,
                static fn (Entity $customEntity) => $customEntity->getName() === $ceName
            )
        )[0];
    }

    /**
     * @param EntityRepository<CustomEntityCollection>|null $customEntityRepository
     */
    private function createLifecycleService(
        Connection $connection,
        ?EntityRepository $customEntityRepository = null,
        ?CustomEntitySchemaUpdater $customEntitySchemaUpdater = null
    ): CustomEntityLifecycleService {
        return new CustomEntityLifecycleService(
            $this->createMock(CustomEntityPersister::class),
            $customEntitySchemaUpdater ?? $this->createMock(CustomEntitySchemaUpdater::class),
            new CustomEntityEnrichmentService(new AdminUiXmlSchemaValidator()),
            new CustomEntityXmlSchemaValidator(),
            new StaticSourceResolver([]),
            $connection,
            $customEntityRepository ?? $this->createCustomEntityRepository(),
        );
    }

    /**
     * @return StaticEntityRepository<CustomEntityCollection>
     */
    private function createCustomEntityRepository(CustomEntityEntity ...$customEntities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<CustomEntityCollection> $repository */
        $repository = new StaticEntityRepository([new CustomEntityCollection($customEntities)]);

        return $repository;
    }
}
