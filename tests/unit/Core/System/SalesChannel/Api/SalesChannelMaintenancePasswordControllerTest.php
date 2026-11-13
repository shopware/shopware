<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Api\SalesChannelMaintenancePasswordController;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelMaintenancePasswordController::class)]
class SalesChannelMaintenancePasswordControllerTest extends TestCase
{
    private Context $adminContext;

    protected function setUp(): void
    {
        $this->adminContext = Context::createDefaultContext(new AdminApiSource(null));
    }

    public function testStatusReportsAStoredPassword(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [
            [$salesChannelId],
            static function (Criteria $criteria) use ($salesChannelId): array {
                static::assertEquals(
                    [new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('maintenancePassword', null)])],
                    $criteria->getFilters()
                );

                return [$salesChannelId];
            },
        ]);

        $response = (new SalesChannelMaintenancePasswordController($repository))->status($this->adminContext, $salesChannelId);

        static::assertSame('{"isSet":true}', $response->getContent());
    }

    public function testStatusReportsAMissingPassword(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [[$salesChannelId], []]);

        $response = (new SalesChannelMaintenancePasswordController($repository))->status($this->adminContext, $salesChannelId);

        static::assertSame('{"isSet":false}', $response->getContent());
    }

    public function testStatusThrowsForAnUnknownSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [[]]);

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound($salesChannelId));

        (new SalesChannelMaintenancePasswordController($repository))->status($this->adminContext, $salesChannelId);
    }

    public function testStatusThrowsForAnInvalidSalesChannelId(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelCollection::class);

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound('not-a-uuid'));

        (new SalesChannelMaintenancePasswordController($repository))->status($this->adminContext, 'not-a-uuid');
    }

    public function testStatusIsOnlyAvailableForAdminApiSources(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelCollection::class);

        $this->expectExceptionObject(SalesChannelException::maintenancePasswordInvalidSource());

        (new SalesChannelMaintenancePasswordController($repository))->status(Context::createDefaultContext(), Uuid::randomHex());
    }
}
