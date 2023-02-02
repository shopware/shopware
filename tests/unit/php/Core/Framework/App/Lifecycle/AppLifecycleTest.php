<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\AppLifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppDefinition;
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
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\AppLifecycle
 */
class AppLifecycleTest extends TestCase
{
    /**
     * @dataProvider installDataProvider
     */
    public function testInstallSavesSnippets(
        Manifest $manifest,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $aclRoleRepository,
        AppAdministrationSnippetPersister $appAdministrationSnippetPersister,
        EntityRepositoryInterface $languageRepository,
        AbstractAppLoader $appLoader
    ): void {
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $aclRoleRepository,
            $languageRepository,
            $appAdministrationSnippetPersister,
            $appLoader
        );
        $appLifecycle->install($manifest, false, Context::createDefaultContext());

        // assert no exception was thrown
        static::assertTrue(true);
    }

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdateSavesSnippets(
        Manifest $manifest,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $aclRoleRepository,
        AppAdministrationSnippetPersister $appAdministrationSnippetPersister,
        EntityRepositoryInterface $languageRepository,
        AbstractAppLoader $appLoader
    ): void {
        $appLifecycle = $this->getAppLifecycle(
            $appRepository,
            $aclRoleRepository,
            $languageRepository,
            $appAdministrationSnippetPersister,
            $appLoader
        );
        // appId will be overwritten by the result of the appRepository and therefore is only a placeholder
        $appLifecycle->update($manifest, ['id' => 'appId', 'roleId' => 'roleId'], Context::createDefaultContext());

        // assert no exception was thrown
        static::assertTrue(true);
    }

    /**
     * @return array<string, array{manifest: Manifest, appRepository: EntityRepositoryInterface, aclRoleRepository: EntityRepositoryInterface, appAdministrationSnippetPersister: AppAdministrationSnippetPersister, languageRepository: EntityRepositoryInterface, appLoader: AbstractAppLoader}>
     */
    public function installDataProvider(): iterable
    {
        $appEntities = [
            [],
            [
                [
                    'id' => Uuid::randomHex(),
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

        yield 'Snippets are given' => [
            'manifest' => Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml'),
            'appRepository' => $this->getAppRepositoryMock($appEntities),
            'aclRoleRepository' => $this->getAclRoleRepositoryMock($this->getAclRoleCollection()),
            'appAdministrationSnippetPersister' => $this->getAppAdministrationSnippetPersisterMock($appEntities[2], $this->getSnippets()),
            'languageRepository' => $this->getLanguageRepositoryMock($this->getLanguageCollection(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
                    ],
                ]
            )),
            'appLoader' => $this->getAppLoaderMock($this->getSnippets()),
        ];

        yield 'No snippets are given' => [
            'manifest' => Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml'),
            'appRepository' => $this->getAppRepositoryMock($appEntities),
            'aclRoleRepository' => $this->getAclRoleRepositoryMock($this->getAclRoleCollection()),
            'appAdministrationSnippetPersister' => $this->getAppAdministrationSnippetPersisterMock($appEntities[2], []),
            'languageRepository' => $this->getLanguageRepositoryMock($this->getLanguageCollection(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
                    ],
                ]
            )),
            'appLoader' => $this->getAppLoaderMock([]),
        ];
    }

    /**
     * @return array<string, array{manifest: Manifest, appRepository: EntityRepositoryInterface, aclRoleRepository: EntityRepositoryInterface, appAdministrationSnippetPersister: AppAdministrationSnippetPersister, languageRepository: EntityRepositoryInterface, appLoader: AbstractAppLoader}>
     */
    public function updateDataProvider(): iterable
    {
        $appId = Uuid::randomHex();
        $appEntities = [
            [
                [
                    'id' => $appId,
                ],
            ],
            [
                [
                    'id' => $appId,
                    'name' => 'test',
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

        yield 'Snippets are given' => [
            'manifest' => Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml'),
            'appRepository' => $this->getAppRepositoryMock($appEntities),
            'aclRoleRepository' => $this->getAclRoleRepositoryMock($this->getAclRoleCollection()),
            'appAdministrationSnippetPersister' => $this->getAppAdministrationSnippetPersisterMock($appEntities[2], $this->getSnippets()),
            'languageRepository' => $this->getLanguageRepositoryMock($this->getLanguageCollection(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
                    ],
                ]
            )),
            'appLoader' => $this->getAppLoaderMock($this->getSnippets()),
        ];

        yield 'No snippets are given' => [
            'manifest' => Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml'),
            'appRepository' => $this->getAppRepositoryMock($appEntities),
            'aclRoleRepository' => $this->getAclRoleRepositoryMock($this->getAclRoleCollection()),
            'appAdministrationSnippetPersister' => $this->getAppAdministrationSnippetPersisterMock($appEntities[2], []),
            'languageRepository' => $this->getLanguageRepositoryMock($this->getLanguageCollection(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'locale' => $this->getLocaleEntity(['code' => 'en-GB']),
                    ],
                ]
            )),
            'appLoader' => $this->getAppLoaderMock([]),
        ];
    }

    private function getAppLifecycle(
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $aclRoleRepository,
        EntityRepositoryInterface $languageRepository,
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
            $this->createMock(RuleConditionPersister::class),
            $this->createMock(CmsBlockPersister::class),
            $appLoader,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AppRegistrationService::class),
            $this->createMock(AppStateService::class),
            $languageRepository,
            $this->createMock(SystemConfigService::class),
            $this->createMock(ConfigValidator::class),
            $this->createMock(EntityRepositoryInterface::class),
            $aclRoleRepository,
            $this->createMock(AssetService::class),
            $this->createMock(ScriptExecutor::class),
            __DIR__,
            $this->createMock(CustomEntityPersister::class),
            $this->createMock(CustomEntitySchemaUpdater::class),
            $this->createMock(Connection::class),
            $this->createMock(FlowActionPersister::class),
            $appAdministrationSnippetPersisterMock,
        );
    }

    private function getLanguageRepositoryMock(LanguageCollection $languageEntityCollection): EntityRepositoryInterface
    {
        $languageRepository = $this->createMock(EntityRepositoryInterface::class);

        $entitySearchResult = new EntitySearchResult(
            LanguageDefinition::ENTITY_NAME,
            $languageEntityCollection->count(),
            $languageEntityCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $languageRepository
            ->method('search')
            ->willReturn($entitySearchResult);

        return $languageRepository;
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
     */
    private function getAppRepositoryMock(array $appEntities): EntityRepositoryInterface
    {
        $appRepository = $this->createMock(EntityRepositoryInterface::class);

        $searchResults = [];
        foreach ($appEntities as $entity) {
            $collection = $this->getAppCollection($entity);

            $searchResults[] = new EntitySearchResult(
                AppDefinition::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                Context::createDefaultContext()
            );
        }

        $appRepository
            ->method('search')
            ->willReturnOnConsecutiveCalls(...$searchResults);

        return $appRepository;
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

            if (\array_key_exists('id', $entity)) {
                $appEntity->setUniqueIdentifier($entity['id']);
            }

            $entities[] = $appEntity;
        }

        return new AppCollection($entities);
    }

    private function getAclRoleRepositoryMock(AclRoleCollection $aclRoleCollection): EntityRepositoryInterface
    {
        $aclRoleRepository = $this->createMock(EntityRepositoryInterface::class);

        $entitySearchResult = new EntitySearchResult(
            AclRoleDefinition::ENTITY_NAME,
            $aclRoleCollection->count(),
            $aclRoleCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $aclRoleRepository
            ->method('search')
            ->willReturn($entitySearchResult);

        return $aclRoleRepository;
    }

    /**
     * @param array<int, array<string, mixed>> $aclRoleEntities
     */
    private function getAclRoleCollection(array $aclRoleEntities = []): AclRoleCollection
    {
        $entities = [];

        foreach ($aclRoleEntities as $entity) {
            $aclRoleEntity = new AclRoleEntity();
            $aclRoleEntity->assign($entity);

            $entities[] = $aclRoleEntity;
        }

        return new AclRoleCollection($entities);
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
    private function getSnippets(): array
    {
        return [
            'en-GB' => [
                'snippetKey' => 'snippetTranslation',
            ],
        ];
    }
}
