<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Handler\DocumentTypeLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class DocumentTypeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const APP_DIR = __DIR__ . '/_fixtures/withDocumentTypes';

    private const APP_DIR_V2 = __DIR__ . '/_fixtures/withDocumentTypesV2';

    private const APP_DIR_CORE_COLLISION = __DIR__ . '/_fixtures/withCoreCollision';

    private DocumentTypeLifecycleHandler $handler;

    /**
     * @var EntityRepository<AppDocumentTypeCollection>
     */
    private EntityRepository $appDocumentTypeRepository;

    private AppFixture $appFixture;

    private Context $context;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(DocumentTypeLifecycleHandler::class);

        /** @var EntityRepository<AppDocumentTypeCollection> $repository */
        $repository = static::getContainer()->get('app_document_type.repository');
        $this->appDocumentTypeRepository = $repository;

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;

        $this->context = Context::createDefaultContext();
    }

    public function testInstallCreatesDocumentTypesFromManifest(): void
    {
        $manifest = $this->appFixture->loadManifest(self::APP_DIR . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        $documentTypes = $this->getDocumentTypes($app->getId());
        static::assertCount(2, $documentTypes);

        $withConfig = $documentTypes->filterByProperty('technicalName', 'swag_type_with_config')->first();
        static::assertNotNull($withConfig);
        static::assertSame('Type with config', $withConfig->getLabel());
        static::assertSame(['itemsPerPage' => 10, 'displayHeader' => true], $withConfig->getConfig());
        static::assertSame(['html', 'pdf'], $withConfig->getFormats());

        $withoutConfig = $documentTypes->filterByProperty('technicalName', 'swag_type_without_config')->first();
        static::assertNotNull($withoutConfig);
        static::assertSame('Type without config', $withoutConfig->getLabel());
        static::assertNull($withoutConfig->getConfig());
        static::assertSame(['html'], $withoutConfig->getFormats());
    }

    public function testUpdateReconcilesDroppedAndKeptDocumentTypes(): void
    {
        $manifest = $this->appFixture->loadManifest(self::APP_DIR . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        $documentTypes = $this->getDocumentTypes($app->getId());
        $keptId = $documentTypes->filterByProperty('technicalName', 'swag_type_with_config')->first()?->getId();
        static::assertNotNull($keptId);

        $manifestV2 = $this->appFixture->loadManifest(self::APP_DIR_V2 . '/manifest.xml');
        $this->handler->update($this->appFixture->createUpdateContext($app, $manifestV2));

        $updatedDocumentTypes = $this->getDocumentTypes($app->getId());
        static::assertCount(1, $updatedDocumentTypes);

        $kept = $updatedDocumentTypes->filterByProperty('technicalName', 'swag_type_with_config')->first();
        static::assertNotNull($kept);
        static::assertSame($keptId, $kept->getId(), 'update must reuse the stable id instead of duplicating the row');
        static::assertSame('Type with config, updated', $kept->getLabel());
        static::assertSame(['itemsPerPage' => 20, 'displayHeader' => false], $kept->getConfig());
        static::assertSame(['html'], $kept->getFormats());

        static::assertNull($updatedDocumentTypes->filterByProperty('technicalName', 'swag_type_without_config')->first());
    }

    public function testDocumentTypesAreRemovedWhenAppIsDeleted(): void
    {
        $manifest = $this->appFixture->loadManifest(self::APP_DIR . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        static::assertCount(2, $this->getDocumentTypes($app->getId()));

        static::getContainer()->get('app.repository')->delete([['id' => $app->getId()]], $this->context);

        static::assertCount(0, $this->getDocumentTypes($app->getId()));
    }

    public function testInstallSkipsDocumentTypesCollidingWithCoreIdentifiers(): void
    {
        $manifest = $this->appFixture->loadManifest(self::APP_DIR_CORE_COLLISION . '/manifest.xml');
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        $documentTypes = $this->getDocumentTypes($app->getId());
        static::assertCount(1, $documentTypes);

        static::assertNull($documentTypes->filterByProperty('technicalName', 'invoice')->first());

        $valid = $documentTypes->filterByProperty('technicalName', 'swag_valid_type')->first();
        static::assertNotNull($valid);
        static::assertSame('Valid type', $valid->getLabel());
    }

    public function testInstallRejectsDocumentTypeIdentifierClaimedByAnotherApp(): void
    {
        $manifest = $this->appFixture->loadManifest(self::APP_DIR . '/manifest.xml');
        $owningApp = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($owningApp, $manifest));

        $competingApp = $this->appFixture->createAppFromData(['name' => 'CompetingApp']);

        $this->expectExceptionObject(AppException::documentTypeAlreadyRegistered(
            'swag_type_with_config',
            $owningApp->getName(),
        ));

        $this->handler->install($this->appFixture->createInstallContext($competingApp, $manifest));
    }

    private function getDocumentTypes(string $appId): AppDocumentTypeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->appDocumentTypeRepository->search($criteria, $this->context)->getEntities();
    }
}
