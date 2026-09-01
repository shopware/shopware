<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChildCountUpdater::class)]
class ChildCountUpdaterTest extends TestCase
{
    public function testUpdateWritesEachChildCountAndResetsParentsWithoutChildren(): void
    {
        $withChildren = Uuid::randomHex();
        $emptied = Uuid::randomHex();
        $singleChild = Uuid::randomHex();

        // the GROUP BY only returns parents that still have children; $emptied is absent
        $written = $this->captureChildCountWrite(
            aggregationByHex: [$withChildren => 3, $singleChild => 1],
            parentIds: [$withChildren, $emptied, $singleChild],
            versionAware: false,
        );

        static::assertSame(
            [$withChildren => 3, $emptied => 0, $singleChild => 1],
            $written,
            'Every listed parent must be written, and a parent that lost its last child must be reset to 0.',
        );
    }

    public function testUpdateDoesNotTouchTheDatabaseWhenNoParentIdsAreGiven(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');
        $connection->expects($this->never())->method('executeStatement');
        $connection->expects($this->never())->method('transactional');

        (new ChildCountUpdater($registry, $connection))->update('category', [], Context::createDefaultContext());
    }

    public function testUpdateScopesTheRecalculationToTheContextVersionForVersionAwareEntities(): void
    {
        $parent = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $captured = [];
        $updater = $this->createUpdater(
            aggregationByHex: [$parent => 2],
            versionAware: true,
            captured: $captured,
        );

        $updater->update('category', [$parent], $context);

        static::assertSame(2, $captured['count0'] ?? null, 'The child count of the version-aware parent must be written.');
        static::assertArrayHasKey('version', $captured, 'A version-aware recalculation must be scoped to the context version.');
        static::assertSame(Uuid::fromHexToBytes($context->getVersionId()), $captured['version']);
    }

    /**
     * Drives the public {@see ChildCountUpdater::update()} and returns the resulting
     * `parentId (hex) => child_count` map, reconstructed from the single UPDATE the updater emits.
     * The write is the only observable effect and the class exposes no other seam.
     *
     * @param array<string, int> $aggregationByHex parents (hex) that still have children, with their child count
     * @param list<string> $parentIds
     *
     * @return array<string, int>
     */
    private function captureChildCountWrite(array $aggregationByHex, array $parentIds, bool $versionAware): array
    {
        $captured = [];
        $this->createUpdater($aggregationByHex, $versionAware, $captured)
            ->update('category', $parentIds, Context::createDefaultContext());

        $written = [];
        foreach ($captured as $key => $value) {
            if (preg_match('/^id(\d+)$/', $key, $matches) === 1) {
                $written[Uuid::fromBytesToHex((string) $value)] = $captured['count' . $matches[1]];
            }
        }

        return $written;
    }

    /**
     * @param array<string, int> $aggregationByHex
     * @param array<string, mixed> $captured captured params of the emitted UPDATE, by reference
     */
    private function createUpdater(array $aggregationByHex, bool $versionAware, array &$captured): ChildCountUpdater
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('category');
        $definition->method('isVersionAware')->willReturn($versionAware);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        // fetchAllKeyValue() returns the counts keyed by the binary parent_id, as MySQL would
        $aggregation = [];
        foreach ($aggregationByHex as $hex => $count) {
            $aggregation[Uuid::fromHexToBytes($hex)] = $count;
        }

        $connection = static::createStub(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        // the write happens inside a retryable transaction, so the double has to really run the closure
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure) => $closure());
        $connection->method('fetchAllKeyValue')->willReturn($aggregation);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params = []) use (&$captured): int {
            $captured = $params;

            return 1;
        });

        return new ChildCountUpdater($registry, $connection);
    }
}
