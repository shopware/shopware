<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeleteCascadeAppsHandler::class)]
class DeleteCascadeAppsHandlerTest extends TestCase
{
    public function testDeletesExpiredAclRolesAndIntegrations(): void
    {
        $assertExpiredFilter = static function (Criteria $criteria): array {
            $filter = $criteria->getFilters()[0] ?? null;
            static::assertInstanceOf(RangeFilter::class, $filter);
            static::assertSame('deletedAt', $filter->getField());
            static::assertSame('2024-01-01 12:00:00.000', $filter->getParameter(RangeFilter::LTE));

            return ['acl-role-id'];
        };
        $aclRoleRepository = StaticEntityRepository::of(AclRoleCollection::class, [$assertExpiredFilter]);
        $integrationRepository = StaticEntityRepository::of(IntegrationCollection::class, [
            static fn (Criteria $criteria): array => ['integration-id'],
        ]);
        $handler = $this->createHandler($aclRoleRepository, $integrationRepository);

        $handler->run();

        static::assertSame([[['id' => 'acl-role-id']]], $aclRoleRepository->deletes);
        static::assertSame([[['id' => 'integration-id']]], $integrationRepository->deletes);
    }

    public function testDoesNotDeleteWhenRepositoriesReturnNoIds(): void
    {
        $aclRoleRepository = StaticEntityRepository::of(AclRoleCollection::class, [[]]);
        $integrationRepository = StaticEntityRepository::of(IntegrationCollection::class, [[]]);
        $handler = $this->createHandler($aclRoleRepository, $integrationRepository);

        $handler->run();

        static::assertSame([], $aclRoleRepository->deletes);
        static::assertSame([], $integrationRepository->deletes);
    }

    /**
     * @param StaticEntityRepository<AclRoleCollection> $aclRoleRepository
     * @param StaticEntityRepository<IntegrationCollection> $integrationRepository
     */
    private function createHandler(
        StaticEntityRepository $aclRoleRepository,
        StaticEntityRepository $integrationRepository,
    ): DeleteCascadeAppsHandler {
        return new DeleteCascadeAppsHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            static::createStub(LoggerInterface::class),
            $aclRoleRepository,
            $integrationRepository,
            new MockClock(new \DateTimeImmutable('2024-01-02 12:00:00')),
        );
    }
}
