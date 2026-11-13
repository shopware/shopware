<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function testSaveStoresAHashOfThePassword(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [[$salesChannelId]]);

        $response = (new SalesChannelMaintenancePasswordController($repository))
            ->save($this->createSaveRequest('a-valid-password'), $this->adminContext, $salesChannelId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertCount(1, $repository->updates);
        $payload = $repository->updates[0][0];
        static::assertSame($salesChannelId, $payload['id']);
        static::assertIsString($payload['maintenancePassword']);
        static::assertNotSame('a-valid-password', $payload['maintenancePassword']);
        static::assertTrue(password_verify('a-valid-password', $payload['maintenancePassword']));
    }

    public function testSaveRemovesThePasswordForAnEmptyValue(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [[$salesChannelId]]);

        (new SalesChannelMaintenancePasswordController($repository))
            ->save($this->createSaveRequest(''), $this->adminContext, $salesChannelId);

        static::assertSame([[['id' => $salesChannelId, 'maintenancePassword' => null]]], $repository->updates);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPasswordPayloadProvider')]
    public function testSaveRejectsAnInvalidPassword(array $payload): void
    {
        $repository = StaticEntityRepository::of(SalesChannelCollection::class);
        $request = new Request([], [], [], [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->expectExceptionObject(SalesChannelException::maintenancePasswordInvalidLength());

        (new SalesChannelMaintenancePasswordController($repository))->save($request, $this->adminContext, Uuid::randomHex());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPasswordPayloadProvider(): iterable
    {
        yield 'missing password' => [[]];
        yield 'password is not a string' => [['password' => 123456789012]];
        yield 'password is too short' => [['password' => str_repeat('a', 11)]];
        yield 'password is too long' => [['password' => str_repeat('a', 4097)]];
    }

    public function testSaveThrowsForAnUnknownSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(SalesChannelCollection::class, [[]]);

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound($salesChannelId));

        (new SalesChannelMaintenancePasswordController($repository))
            ->save($this->createSaveRequest('a-valid-password'), $this->adminContext, $salesChannelId);
    }

    public function testSaveThrowsForAnInvalidSalesChannelId(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelCollection::class);

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound('not-a-uuid'));

        (new SalesChannelMaintenancePasswordController($repository))
            ->save($this->createSaveRequest('a-valid-password'), $this->adminContext, 'not-a-uuid');
    }

    public function testSaveIsOnlyAvailableForAdminApiSources(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelCollection::class);

        $this->expectExceptionObject(SalesChannelException::maintenancePasswordInvalidSource());

        (new SalesChannelMaintenancePasswordController($repository))
            ->save($this->createSaveRequest('a-valid-password'), Context::createDefaultContext(), Uuid::randomHex());
    }

    private function createSaveRequest(string $password): Request
    {
        return new Request([], [], [], [], [], [], json_encode(['password' => $password], \JSON_THROW_ON_ERROR));
    }
}
