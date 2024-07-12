<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Search\CriteriaCollection;
use Shopware\Administration\Service\AdminSearcher;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(AdminSearcher::class)]
class AdminSearcherTest extends TestCase
{
    private MockObject&DefinitionInstanceRegistry $definitionInstanceRegistry;

    private AdminApiSource $source;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->source = new AdminApiSource('test');
        $this->source->setIsAdmin(false);
    }

    public function testAdminSearcherSearchWithEmptyCollection(): void
    {
        $adminSearcher = new AdminSearcher($this->definitionInstanceRegistry);

        $entities = new CriteriaCollection();

        static::assertSame([], $adminSearcher->search($entities, Context::createDefaultContext()));
    }

    public function testAdminSearcherSearchWithCriteriaNotInRegistry(): void
    {
        $this->definitionInstanceRegistry->expects(static::any())->method('has')->willReturn(false);
        $this->definitionInstanceRegistry->expects(static::never())->method('getRepository');

        $adminSearcher = new AdminSearcher($this->definitionInstanceRegistry);
        $queries = new CriteriaCollection(['product' => new Criteria()]);

        static::assertSame([], $adminSearcher->search($queries, Context::createDefaultContext($this->source)));
    }

    public function testAdminSearcherSearchWithNoReadAcl(): void
    {
        $this->definitionInstanceRegistry->expects(static::any())->method('has')->willReturn(true);
        $this->definitionInstanceRegistry->expects(static::never())->method('getRepository');

        $adminSearcher = new AdminSearcher($this->definitionInstanceRegistry);

        $queries = new CriteriaCollection(['product' => new Criteria()]);

        static::assertSame([], $adminSearcher->search($queries, Context::createDefaultContext($this->source)));
    }

    public function testAdminSearcherSearchWithReadAcl(): void
    {
        $this->definitionInstanceRegistry->expects(static::any())->method('has')->willReturn(true);
        $this->definitionInstanceRegistry->expects(static::once())->method('getRepository')->willReturn(
            $this->createMock(EntityRepository::class)
        );

        $this->source->setIsAdmin(true);

        $adminSearcher = new AdminSearcher($this->definitionInstanceRegistry);

        $queries = new CriteriaCollection(['product' => new Criteria()]);

        $result = $adminSearcher->search($queries, Context::createDefaultContext($this->source));

        static::assertCount(1, $result);
        static::assertArrayHasKey('product', $result);

        $productResult = $result['product'];
        static::assertArrayHasKey('data', $productResult);
        static::assertArrayHasKey('total', $productResult);
        static::assertEquals(0, $productResult['total']);
    }
}
