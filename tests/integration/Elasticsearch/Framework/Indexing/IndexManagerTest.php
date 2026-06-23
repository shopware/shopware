<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\Indexing\IndexManager;

/**
 * @internal
 */
class IndexManagerTest extends TestCase
{
    use KernelTestBehaviour;

    #[TestDox('refreshIndices() iterates every registered definition and swallows per-index refresh errors')]
    public function testRefreshIndicesSwallowsPerIndexErrors(): void
    {
        $indexManager = static::getContainer()->get(IndexManager::class);
        static::assertInstanceOf(IndexManager::class, $indexManager);

        // Missing/unreachable indices are caught per definition, so the call must not surface anything,
        // regardless of whether Elasticsearch is reachable in this environment.
        $indexManager->refreshIndices();

        $this->addToAssertionCount(1);
    }
}
