<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(CustomEntityLifecycleService::class)]
class CustomEntityLifecycleServiceTest extends TestCase
{
    public function testResultIsNullIfThereIsNoExtension(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects(static::never())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects(static::never())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            '',
            new StaticSourceResolver([
                'SwagExampleTest' => new StaticFilesystem(),
            ]),
        );

        static::assertNull(
            $customEntityLifecycleService->updatePlugin(Uuid::randomHex(), 'not/given')
        );

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        static::assertNull(
            $customEntityLifecycleService->updateApp($app)
        );
    }

    public function testUpdatePluginOnlyCustomEntities(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects(static::once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects(static::once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            '',
            new StaticSourceResolver(),
        );

        $customEntityXmlSchema = $customEntityLifecycleService->updatePlugin(
            Uuid::randomHex(),
            __DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntities/plugin'
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $customEntityXmlSchema);

        $this->checkFieldsAndFlagsCount($customEntityXmlSchema);
    }

    public function testUpdateAppOnlyCustomEntities(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects(static::once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects(static::once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            '',
            new StaticSourceResolver([
                'SwagExampleTest' => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntities/app'),
            ]),
        );

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', 'id' => 'test']);

        $schema = $customEntityLifecycleService->updateApp($app);

        static::assertInstanceOf(CustomEntityXmlSchema::class, $schema);

        $this->checkFieldsAndFlagsCount($schema);
    }

    public function testUpdatePluginCustomEntitiesWithAdminUi(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects(static::once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects(static::once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            '',
            new StaticSourceResolver(),
        );

        $schema = $customEntityLifecycleService->updatePlugin(
            Uuid::randomHex(),
            __DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntitiesAndAdminUis/plugin'
        );
        static::assertInstanceOf(CustomEntityXmlSchema::class, $schema);

        $this->checkFieldsAndFlagsCount($schema, true);
    }

    public function testUpdateAppCustomEntitiesWithAdminUi(): void
    {
        $customEntityPersister = $this->createMock(CustomEntityPersister::class);
        $customEntityPersister->expects(static::once())->method('update');

        $customEntitySchemaUpdater = $this->createMock(CustomEntitySchemaUpdater::class);
        $customEntitySchemaUpdater->expects(static::once())->method('update');

        $adminUiXmlSchemaValidator = new AdminUiXmlSchemaValidator();
        $customEntityEnrichmentService = new CustomEntityEnrichmentService($adminUiXmlSchemaValidator);

        $customEntityXmlSchemaValidator = new CustomEntityXmlSchemaValidator();

        $customEntityLifecycleService = new CustomEntityLifecycleService(
            $customEntityPersister,
            $customEntitySchemaUpdater,
            $customEntityEnrichmentService,
            $customEntityXmlSchemaValidator,
            '',
            new StaticSourceResolver([
                'SwagExampleTest' => new Filesystem(__DIR__ . '/_fixtures/CustomEntityLifecycleServiceTest/withCustomEntitiesAndAdminUis/app'),
            ]),
        );

        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', 'id' => 'test']);

        $schema = $customEntityLifecycleService->updateApp($app);
        static::assertInstanceOf(CustomEntityXmlSchema::class, $schema);

        $this->checkFieldsAndFlagsCount($schema, true);
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
                fn (Entity $customEntity) => $customEntity->getName() === $ceName
            )
        )[0];
    }
}
