<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\Requirement\ServicesEnabledRequirement;
use Shopware\Core\Service\Subscriber\ServiceWriteProtectionSubscriber;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ServiceWriteProtectionSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testServiceCannotBeDeactivatedThroughTheEntityApi(): void
    {
        $appId = $this->appFixture->createAppFromData(['selfManaged' => true])->getId();

        $browser = $this->getBrowser();
        $browser->jsonRequest('PATCH', '/api/app/' . $appId, ['active' => false], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertBlocked($browser);
        static::assertTrue($this->appFixture->getApp($appId)->isActive());
    }

    public function testServiceCannotBeDeletedThroughTheEntityApi(): void
    {
        $appId = $this->appFixture->createAppFromData(['selfManaged' => true])->getId();

        $browser = $this->getBrowser();
        $browser->jsonRequest('DELETE', '/api/app/' . $appId, [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertBlocked($browser);
        static::assertTrue($this->appFixture->getApp($appId)->isActive());
    }

    public function testAppCannotBeFlaggedAsServiceThroughTheEntityApi(): void
    {
        $appId = $this->appFixture->createAppFromData(['selfManaged' => false])->getId();

        $browser = $this->getBrowser();
        $browser->jsonRequest('PATCH', '/api/app/' . $appId, ['selfManaged' => true], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertBlocked($browser);
        static::assertFalse($this->appFixture->getApp($appId)->isSelfManaged());
    }

    public function testServiceCannotBeCreatedThroughTheEntityApi(): void
    {
        // The guard rejects the write during pre-write validation, before the app row (and its foreign
        // keys) are ever persisted, so the referenced ids do not need to exist.
        $name = 'CreatedViaApi' . Uuid::randomHex();

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/app', [
            'name' => $name,
            'path' => 'foo/bar',
            'version' => '1.0.0',
            'label' => $name,
            'active' => true,
            'selfManaged' => true,
            'integrationId' => Uuid::randomHex(),
            'aclRoleId' => Uuid::randomHex(),
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertBlocked($browser);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        static::assertNull($this->appRepository->searchIds($criteria, Context::createDefaultContext())->firstId());
    }

    public function testRegularAppCanStillBeModifiedThroughTheEntityApi(): void
    {
        $appId = $this->appFixture->createAppFromData(['selfManaged' => false])->getId();

        $browser = $this->getBrowser();
        $browser->jsonRequest('PATCH', '/api/app/' . $appId, ['active' => false], ['HTTP_ACCEPT' => 'application/json']);

        static::assertSame(
            Response::HTTP_NO_CONTENT,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
        static::assertFalse($this->appFixture->getApp($appId)->isActive());
    }

    public function testDisablingServicesUninstallsThemWithoutBeingBlocked(): void
    {
        $appId = $this->appFixture->createAppFromData(
            ['selfManaged' => true, 'sourceConfig' => ['requirements' => [ServicesEnabledRequirement::NAME]],
            ]
        )->getId();

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/services/disable', [], ['HTTP_ACCEPT' => 'application/json']);

        static::assertSame(
            Response::HTTP_OK,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
        static::assertNull($this->appRepository->searchIds(new Criteria([$appId]), Context::createDefaultContext())->firstId());
    }

    private function assertBlocked(TestBrowser $browser): void
    {
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $errors = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'] ?? [];
        $codes = array_column($errors, 'code');

        static::assertContains(
            ServiceWriteProtectionSubscriber::VIOLATION_NO_PERMISSION,
            $codes,
            'Expected the write to be rejected by the service write protection: ' . $response->getContent()
        );
    }
}
