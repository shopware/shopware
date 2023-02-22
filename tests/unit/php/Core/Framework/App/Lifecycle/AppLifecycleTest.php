<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Persister\ActionButtonPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\AppLifecycle
 */
class AppLifecycleTest extends TestCase
{
    public function testInstallSavesSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([self::getLanguageCollection(
            [
                [
                    'id' => Uuid::randomHex(),
                    'locale' => self::getLocaleEntity(['code' => 'en-GB']),
                ],
            ]
        )]);

        $appEntities = [
            [],
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
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
            $this->getAppAdministrationSnippetPersisterMock($appEntities[2], self::getSnippets()),
            $this->getAppLoaderMock($this->getSnippets())
        );

        $appLifecycle->install($manifest, false, Context::createDefaultContext());

        $upsert = $appRepository->getUpsert();

        static::assertCount(1, $upsert);
        static::assertSame('test', $upsert[0]['name']);
    }

    public function testInstallSavesNoSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([self::getLanguageCollection(
            [
                [
                    'id' => Uuid::randomHex(),
                    'locale' => self::getLocaleEntity(['code' => 'en-GB']),
                ],
            ]
        )]);

        $appEntities = [
            [],
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
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
            $this->getAppAdministrationSnippetPersisterMock($appEntities[2]),
            $this->getAppLoaderMock()
        );

        $appLifecycle->install($manifest, false, Context::createDefaultContext());

        $upsert = $appRepository->getUpsert();

        static::assertCount(1, $upsert);
        static::assertSame('test', $upsert[0]['name']);
    }

    public function testUpdateSavesNoSnippetsGiven(): void
    {
        $languageRepository = new StaticEntityRepository([self::getLanguageCollection(
            [
                [
                    'id' => Uuid::randomHex(),
                    'locale' => self::getLocaleEntity(['code' => 'en-GB']),
                ],
            ]
        )]);

        $appEntities = [
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
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
            $this->getAppAdministrationSnippetPersisterMock($appEntities[1]),
            $this->getAppLoaderMock()
        );

        $appLifecycle->update($manifest, ['id' => 'appId', 'roleId' => 'roleId'], Context::createDefaultContext());

        $upsert = $appRepository->getUpsert();

        static::assertCount(1, $upsert);
        static::assertSame('test', $upsert[0]['name']);
    }

    public function testUpdateSavesSnippets(): void
    {
        $languageRepository = new StaticEntityRepository([self::getLanguageCollection(
            [
                [
                    'id' => Uuid::randomHex(),
                    'locale' => self::getLocaleEntity(['code' => 'en-GB']),
                ],
            ]
        )]);

        $appEntities = [
            [
                [
                    'id' => Uuid::randomHex(),
                    'path' => '',
                ],
            ],
            [
                [
                    'id' => Uuid::randomHex(),
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
            $this->getAppAdministrationSnippetPersisterMock($appEntities[1], self::getSnippets()),
            $this->getAppLoaderMock(self::getSnippets())
        );

        $appLifecycle->update($manifest, ['id' => 'appId', 'roleId' => 'roleId'], Context::createDefaultContext());

        $upsert = $appRepository->getUpsert();

        static::assertCount(1, $upsert);
        static::assertSame('test', $upsert[0]['name']);
    }

    private function getAppLifecycle(
        EntityRepository $appRepository,
        EntityRepository $languageRepository,
        AppAdministrationSnippetPersister $appAdministrationSnippetPersisterMock,
        AbstractAppLoader $appLoader
    ): AppLifecycle {
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
            $appLoader,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AppRegistrationService::class),
            $this->createMock(AppStateService::class),
            $languageRepository,
            $this->createMock(SystemConfigService::class),
            $this->createMock(ConfigValidator::class),
            $this->createMock(EntityRepository::class),
            new StaticEntityRepository([new AclRoleCollection()]),
            $this->createMock(AssetService::class),
            $this->createMock(ScriptExecutor::class),
            __DIR__,
            $this->createMock(Connection::class),
            $this->createMock(FlowActionPersister::class),
            $appAdministrationSnippetPersisterMock,
            $this->createMock(CustomEntitySchemaUpdater::class),
            $this->createMock(CustomEntityLifecycleService::class),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $languageEntities
     */
    private static function getLanguageCollection(array $languageEntities = []): LanguageCollection
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
    private static function getLocaleEntity(array $data = []): LocaleEntity
    {
        $localeEntity = new LocaleEntity();

        $localeEntity->assign($data);

        return $localeEntity;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $appEntities
     */
    private function getAppRepositoryMock(array $appEntities): StaticEntityRepository
    {
        $searchResults = [];
        foreach ($appEntities as $entity) {
            $searchResults[] = $this->getAppCollection($entity);
        }

        return new StaticEntityRepository($searchResults);
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
     * @param array<int, array<string, string>> $appEntities
     * @param array<string, array<string, string>> $expectedSnippets
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

    /**
     * @param array<string, array<string, string>> $snippets
     */
    private function getAppLoaderMock(array $snippets = []): AbstractAppLoader
    {
        $appLoader = $this->createMock(AbstractAppLoader::class);

        $appLoader
            ->method('getSnippets')
            ->willReturn($snippets);

        return $appLoader;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function getSnippets(): array
    {
        return [
            'en-GB' => [
                'snippetKey' => 'snippetTranslation',
            ],
        ];
    }
}
