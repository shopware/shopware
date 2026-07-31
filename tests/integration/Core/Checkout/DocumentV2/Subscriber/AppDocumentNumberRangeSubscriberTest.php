<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class AppDocumentNumberRangeSubscriberTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<NumberRangeTypeCollection>
     */
    private EntityRepository $numberRangeTypeRepository;

    /**
     * @var EntityRepository<NumberRangeCollection>
     */
    private EntityRepository $numberRangeRepository;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppManager $appManager;

    private Context $context;

    protected function setUp(): void
    {
        $this->numberRangeTypeRepository = static::getContainer()->get('number_range_type.repository');
        $this->numberRangeRepository = static::getContainer()->get('number_range.repository');
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->appManager = static::getContainer()->get(AppManager::class);
        $this->context = Context::createDefaultContext();
    }

    public function testInstallingAppSeedsNumberRangeForNonCoreDocumentType(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/withCertificateType');

        $type = $this->numberRangeTypeRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'document_swag_certificate')),
            $this->context
        )->getEntities()->first();

        static::assertNotNull($type);
        static::assertSame('document_swag_certificate', $type->getTechnicalName());
        static::assertTrue($type->getGlobal());

        $typeId = $type->getId();

        $range = $this->numberRangeRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', $typeId)),
            $this->context
        )->getEntities()->first();

        static::assertNotNull($range);
        static::assertTrue($range->isGlobal());
        static::assertSame('{n}', $range->getPattern());
        static::assertSame(1000, $range->getStart());
    }

    public function testInstallingAppWithCoreCollidingIdentifierDoesNotTouchCoreRange(): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', 'document_invoice'));

        $before = $this->numberRangeTypeRepository->searchIds($criteria, $this->context);
        static::assertCount(1, $before->getIds());
        $coreTypeId = $before->firstId();

        $this->loadAppsFromDir(__DIR__ . '/_fixtures/withCoreCollisionType');

        $after = $this->numberRangeTypeRepository->searchIds($criteria, $this->context);
        static::assertCount(1, $after->getIds());
        static::assertSame($coreTypeId, $after->firstId());
    }

    public function testUpdatingAnAppSeedsNumberRangeForNewlyDeclaredDocumentType(): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', 'document_swag_certificate'));
        static::assertNull($this->numberRangeTypeRepository->searchIds($criteria, $this->context)->firstId());

        $this->appManager->install(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/updatableApp/v1/manifest.xml'),
            new AppInstallParameters(),
            $this->context
        );

        static::assertNull($this->numberRangeTypeRepository->searchIds($criteria, $this->context)->firstId());

        $app = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'updatableApp')),
            $this->context
        )->getEntities()->first();

        static::assertInstanceOf(AppEntity::class, $app);

        $this->appManager->update(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/updatableApp/v2/manifest.xml'),
            new AppUpdateParameters(),
            $app,
            $this->context
        );

        $type = $this->numberRangeTypeRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertNotNull($type);
        static::assertTrue($type->getGlobal());

        $range = $this->numberRangeRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', $type->getId())),
            $this->context
        )->getEntities()->first();

        static::assertNotNull($range);
        static::assertSame('{n}', $range->getPattern());
        static::assertSame(1000, $range->getStart());
    }
}
