<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Persister\ActionButtonPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowEventPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AppLifecycle::class)]
class AppLifecycleTest extends TestCase
{
    private Io $io;

    protected function setUp(): void
    {
        $this->io = new Io();
        $this->io->mkdir(__DIR__ . '/../_fixtures/Resources/app/administration/snippet');
    }

    protected function tearDown(): void
    {
        $this->io->remove(__DIR__ . '/../_fixtures/Resources/app/administration/snippet');
    }

    public function testInstallNotCompatibleApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->expects(static::never())->method('upsert');

        $appLifecycle = $this->getAppLifecycle($appRepository, new StaticEntityRepository([]), null, new StaticSourceResolver());

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App test is not compatible with this Shopware version');
        $appLifecycle->install($manifest, false, Context::createDefaultContext());
    }

    public function testUpdateNotCompatibleApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->expects(static::never())->method('upsert');

        $appLifecycle = $this->getAppLifecycle($appRepository, new StaticEntityRepository([]), null, new StaticSourceResolver());

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App test is not compatible with this Shopware version');
        $appLifecycle->update($manifest, ['id' => 'test', 'roleId' => 'test'], Context::createDefaultContext());
    }

    public function testInstallSavesSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([$this->getLanguageCollection([
            [
                'id' => Uuid::randomHex(),
                'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
            ],
        ])]);

        $appEntities = [
            [],
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test',
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $this->io->dumpFile(
            __DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json',
            (string) json_encode([
                'snippetKey' => 'snippetTranslation',
            ])
        );

        $appRepository = $this->getAppRepositoryMock($appEntities);
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $languageRepository,
            $this->getAppAdministrationSnippetPersisterMock($appEntities[2], [
                'en-GB' => '{"snippetKey":"snippetTranslation"}',
            ]),
            $this->getSourceResolver(__DIR__ . '/../_fixtures/manifest.xml')
        );

        $appLifecycle->install($manifest, false, Context::createDefaultContext());

        static::assertCount(1, $appRepository->upserts[0]);
        static::assertSame('test', $appRepository->upserts[0][0]['name']);
    }

    public function testInstallSavesNoSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([$this->getLanguageCollection([
            [
                'id' => Uuid::randomHex(),
                'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
            ],
        ])]);

        $appEntities = [
            [],
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test',
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $appRepository = $this->getAppRepositoryMock($appEntities);
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $languageRepository,
            $this->getAppAdministrationSnippetPersisterMock($appEntities[2]),
            $this->getSourceResolver(__DIR__ . '/../_fixtures/manifest.xml')
        );

        $appLifecycle->install($manifest, false, Context::createDefaultContext());

        static::assertCount(1, $appRepository->upserts[0]);
        static::assertSame('test', $appRepository->upserts[0][0]['name']);
    }

    public function testUpdateSavesNoSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([$this->getLanguageCollection([
            [
                'id' => Uuid::randomHex(),
                'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
            ],
        ])]);

        $appEntities = [
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test',
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $appRepository = $this->getAppRepositoryMock($appEntities);
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $languageRepository,
            $this->getAppAdministrationSnippetPersisterMock($appEntities[1]),
            $this->getSourceResolver(__DIR__ . '/../_fixtures/manifest.xml')
        );

        $appLifecycle->update($manifest, ['id' => 'appId', 'roleId' => 'roleId'], Context::createDefaultContext());

        static::assertCount(1, $appRepository->upserts[0]);
        static::assertSame('test', $appRepository->upserts[0][0]['name']);
    }

    public function testUpdateSavesSnippets(): void
    {
        $languageRepository = new StaticEntityRepository([$this->getLanguageCollection([
            [
                'id' => Uuid::randomHex(),
                'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
            ],
        ])]);

        $appEntities = [
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test',
                    'path' => '',
                    'configurable' => false,
                    'allowDisable' => true,
                ],
            ],
        ];

        $this->io->dumpFile(
            __DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json',
            (string) json_encode([
                'snippetKey' => 'snippetTranslation',
            ])
        );

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $appRepository = $this->getAppRepositoryMock($appEntities);
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $languageRepository,
            $this->getAppAdministrationSnippetPersisterMock($appEntities[1], [
                'en-GB' => '{"snippetKey":"snippetTranslation"}',
            ]),
            $this->getSourceResolver(__DIR__ . '/../_fixtures/manifest.xml')
        );

        $appLifecycle->update($manifest, ['id' => 'appId', 'roleId' => 'roleId'], Context::createDefaultContext());

        static::assertCount(1, $appRepository->upserts[0]);
        static::assertSame('test', $appRepository->upserts[0][0]['name']);
    }

    public function testUpdateResetsConfigurableFlagToFalseWhenConfigXMLWasRemoved(): void
    {
        $this->io->rename(__DIR__ . '/../_fixtures/Resources/config', __DIR__ . '/../_fixtures/Resources/noconfighere');

        $languageRepository = new StaticEntityRepository([$this->getLanguageCollection([
            [
                'id' => Uuid::randomHex(),
                'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
            ],
        ])]);

        $appId = Uuid::randomHex();

        $appEntities = [
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                ],
            ],
            [
                [
                    'id' => $appId,
                    'name' => 'test',
                    'path' => '',
                ],
            ],
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $appRepository = $this->getAppRepositoryMock($appEntities);
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $languageRepository,
            null,
            $this->getSourceResolver(__DIR__ . '/../_fixtures/manifest.xml')
        );

        $appLifecycle->update($manifest, ['id' => $appId, 'roleId' => 'roleId'], Context::createDefaultContext());

        static::assertCount(1, $appRepository->upserts[0]);

        static::assertEquals([['id' => $appId, 'configurable' => false, 'allowDisable' => true]], $appRepository->upserts[1]);

        $this->io->rename(__DIR__ . '/../_fixtures/Resources/noconfighere', __DIR__ . '/../_fixtures/Resources/config');
    }

    private function getAppLifecycle(
        EntityRepository $appRepository,
        EntityRepository $languageRepository,
        ?AppAdministrationSnippetPersister $appAdministrationSnippetPersisterMock,
        StaticSourceResolver $appSourceResolver
    ): AppLifecycle {
        /** @var StaticEntityRepository<AclRoleCollection> $aclRoleRepo */
        $aclRoleRepo = new StaticEntityRepository([new AclRoleCollection()]);

        return new AppLifecycle(
            $appRepository,
            $this->createMock(PermissionPersister::class),
            $this->createMock(CustomFieldPersister::class),
            $this->createMock(ActionButtonPersister::class),
            $this->createMock(TemplatePersister::class),
            $this->createMock(ScriptPersister::class),
            $this->createMock(WebhookPersister::class),
            $this->createMock(PaymentMethodPersister::class),
            $this->createMock(TaxProviderPersister::class),
            $this->createMock(RuleConditionPersister::class),
            $this->createMock(CmsBlockPersister::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AppRegistrationService::class),
            $this->createMock(AppStateService::class),
            $languageRepository,
            $this->createMock(SystemConfigService::class),
            $this->createMock(ConfigValidator::class),
            $this->createMock(EntityRepository::class),
            $aclRoleRepo,
            $this->createMock(AssetService::class),
            $this->createMock(ScriptExecutor::class),
            __DIR__,
            $this->createMock(Connection::class),
            $this->createMock(FlowActionPersister::class),
            $appAdministrationSnippetPersisterMock,
            $this->createMock(CustomEntitySchemaUpdater::class),
            $this->createMock(CustomEntityLifecycleService::class),
            '6.5.0.0',
            $this->createMock(FlowEventPersister::class),
            'test',
            $this->createMock(ShippingMethodPersister::class),
            $this->createMock(EntityRepository::class),
            $appSourceResolver,
            $this->createMock(ConfigReader::class)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $languageEntities
     */
    private function getLanguageCollection(array $languageEntities = []): LanguageCollection
    {
        $entities = [];

        foreach ($languageEntities as $entity) {
            $languageEntity = new LanguageEntity();
            $languageEntity->assign($entity);

            $entities[] = $languageEntity;
        }

        return new LanguageCollection($entities);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getLocaleEntity(array $data = []): LocaleEntity
    {
        $localeEntity = new LocaleEntity();

        $localeEntity->assign($data);

        return $localeEntity;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $appEntities
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function getAppRepositoryMock(array $appEntities): StaticEntityRepository
    {
        $searchResults = [];
        foreach ($appEntities as $entity) {
            $searchResults[] = $this->getAppCollection($entity);
        }

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository($searchResults);

        return $repo;
    }

    /**
     * @param array<int, array<string, mixed>> $appEntities
     */
    private function getAppCollection(array $appEntities): AppCollection
    {
        $entities = [];

        foreach ($appEntities as $entity) {
            $appEntity = new AppEntity();
            $appEntity->assign($entity);
            $appEntity->setUniqueIdentifier($entity['id']);

            $entities[] = $appEntity;
        }

        return new AppCollection($entities);
    }

    /**
     * @param array<int, array<string, mixed>> $appEntities
     * @param array<string, string> $expectedSnippets
     */
    private function getAppAdministrationSnippetPersisterMock(array $appEntities, array $expectedSnippets = []): AppAdministrationSnippetPersister
    {
        $appEntities = $this->getAppCollection($appEntities)->first();

        $persister = $this->createMock(AppAdministrationSnippetPersister::class);

        $persister
            ->expects(static::once())
            ->method('updateSnippets')
            ->with($appEntities, $expectedSnippets, Context::createDefaultContext());

        return $persister;
    }

    private function getSourceResolver(string $manifestPath): StaticSourceResolver
    {
        return new StaticSourceResolver([
            'test' => new Filesystem(\dirname($manifestPath)),
        ]);
    }
}
