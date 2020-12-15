<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Search\ExtensionCriteria;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\ReviewCollection;
use Shopware\Core\Framework\Store\Struct\ReviewSummaryStruct;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ExtensionDataProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;
    use ExtensionBehaviour;

    /**
     * @var AbstractExtensionDataProvider
     */
    private $extensionDataProvider;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        $this->extensionDataProvider = $this->getContainer()->get(AbstractExtensionDataProvider::class);
        $this->context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $this->installApp(__DIR__ . '/../_fixtures/TestApp');
    }

    protected function tearDown(): void
    {
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');
    }

    public function testGetListingFilters(): void
    {
        $requestHandler = $this->getRequestHandler();
        $requestHandler->reset();
        $requestHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/filter.json')));

        $filters = $this->extensionDataProvider->getListingFilters(Context::createDefaultContext());

        static::assertSame(json_decode(file_get_contents(__DIR__ . '/../_fixtures/responses/filter.json'), true), $filters);
    }

    public function testItReturnsAListing(): void
    {
        $this->setListingResponse();

        $criteria = ExtensionCriteria::fromArray([
            'limit' => 10,
            'page' => 1,
        ]);

        $listing = $this->extensionDataProvider->getListing($criteria, $this->context);

        static::assertInstanceOf(ExtensionCollection::class, $listing);
        static::assertEquals(2, $listing->count());
    }

    public function testItReturnsInstalledAppsAsExtensionCollection(): void
    {
        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);
        $installedExtension = $installedExtensions->get('TestApp');

        static::assertInstanceOf(ExtensionStruct::class, $installedExtension);
        static::assertNull($installedExtension->getId());
        static::assertEquals('Swag App Test', $installedExtension->getLabel());
    }

    public function testItReturnsAnExtensionDetail(): void
    {
        $extensionId = 12161;

        $this->setDetailResponse($extensionId);
        $extensionDetail = $this->extensionDataProvider->getExtensionDetails($extensionId, $this->context);

        static::assertNotNull($extensionDetail);
        static::assertEquals($extensionId, $extensionDetail->getId());
        static::assertEquals('Change your privacy policy!', $extensionDetail->getPrivacyPolicyExtensions());
    }

    public function testItReturnsReviewsForExtension(): void
    {
        $extensionId = 12161;

        $this->setReviewsResponse($extensionId);
        $extensionReviews = $this->extensionDataProvider->getReviews($extensionId, new ExtensionCriteria(), $this->context);

        static::assertInstanceOf(ReviewCollection::class, $extensionReviews['reviews']);
        static::assertInstanceOf(ReviewSummaryStruct::class, $extensionReviews['summary']);
        static::assertCount(3, $extensionReviews['reviews']);
        static::assertEquals(7, $extensionReviews['summary']->getNumberOfRatings());
    }

    public function testGetAppEntityFromTechnicalName(): void
    {
        static::assertInstanceOf(AppEntity::class, $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context));
    }

    public function testGetAppEntityFromId(): void
    {
        $installedApp = $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context);

        $app = $this->extensionDataProvider->getAppEntityFromId($installedApp->getId(), $this->context);
        static::assertEquals(
            $installedApp,
            $app
        );
    }

    public function testGetAppEntityFromTechnicalNameThrowsIfExtensionCantBeFound(): void
    {
        static::expectException(ExtensionNotFoundException::class);
        $this->extensionDataProvider->getAppEntityFromTechnicalName(Uuid::randomHex(), $this->context);
    }

    public function testGetAppEntityFromIdThrowsIfExtensionCantBeFound(): void
    {
        static::expectException(ExtensionNotFoundException::class);
        $this->extensionDataProvider->getAppEntityFromId(Uuid::randomHex(), $this->context);
    }

    private function setReviewsResponse($extensionId): void
    {
        $requestHandler = $this->getRequestHandler();
        $requestHandler->reset();
        $requestHandler->append(
            function (Request $request) use ($extensionId): Response {
                $matches = [];
                preg_match('/\/swplatform\/extensionstore\/extensions\/(.*)\/reviews/', $request->getUri()->getPath(), $matches);

                static::assertEquals(
                    $extensionId,
                    $matches[1]
                );

                return new Response(
                    200,
                    [],
                    \file_get_contents(__DIR__ . '/../_fixtures/responses/extension-reviews.json')
                );
            }
        );
    }

    private function setListingResponse(): void
    {
        $requestHandler = $this->getRequestHandler();
        $requestHandler->reset();
        $requestHandler->append(new Response(
            200,
            [ExtensionDataProvider::HEADER_NAME_TOTAL_COUNT => '2'],
            \file_get_contents(__DIR__ . '/../_fixtures/responses/extension-listing.json')
        ));
    }

    private function setDetailResponse($extensionId): void
    {
        $requestHandler = $this->getRequestHandler();
        $requestHandler->reset();
        $requestHandler->append(
            function (Request $request) use ($extensionId): Response {
                $matches = [];
                preg_match('/\/swplatform\/extensionstore\/extensions\/(.*)/', $request->getUri()->getPath(), $matches);

                static::assertEquals(
                    $extensionId,
                    $matches[1]
                );

                return new Response(
                    200,
                    [],
                    \file_get_contents(__DIR__ . '/../_fixtures/responses/extension-detail.json')
                );
            }
        );
    }
}
