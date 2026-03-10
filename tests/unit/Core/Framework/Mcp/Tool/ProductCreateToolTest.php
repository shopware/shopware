<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductCreateTool::class)]
class ProductCreateToolTest extends TestCase
{
    #[TestDox('returns payload preview in dry-run mode')]
    public function testDryRunReturnsPayloadPreview(): void
    {
        $taxId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();

        $tool = $this->createTool($taxId, $currencyId);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-001',
            grossPrice: 119.0,
            taxRate: 19,
            currencyCode: 'EUR',
            stock: 50,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame('Test Product', $data['data']['name']);
        static::assertSame('SW-TEST-001', $data['data']['productNumber']);
        static::assertSame(50, $data['data']['stock']);
        static::assertTrue($data['data']['active']);
        static::assertSame($taxId, $data['data']['taxId']);
        static::assertCount(1, $data['data']['price']);
        static::assertSame($currencyId, $data['data']['price'][0]['currencyId']);
        static::assertEqualsWithDelta(119.0, $data['data']['price'][0]['gross'], 0.01);
        static::assertEqualsWithDelta(100.0, $data['data']['price'][0]['net'], 0.01);
        static::assertTrue($data['data']['price'][0]['linked']);
    }

    #[TestDox('includes description in payload when provided')]
    public function testDescriptionIsIncludedWhenProvided(): void
    {
        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex());
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-005',
            grossPrice: 29.99,
            description: '<p>A nice product</p>',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('<p>A nice product</p>', $data['data']['description']);
    }

    #[TestDox('resolves category names to IDs in dry-run payload')]
    public function testCategoryResolutionAddsCategoryIds(): void
    {
        $catId = Uuid::randomHex();
        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex(), categoryIds: ['Shoes' => $catId]);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-006',
            grossPrice: 59.99,
            categories: 'Shoes',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']['categories']);
        static::assertSame($catId, $data['data']['categories'][0]['id']);
    }

    #[TestDox('skips empty category names from trailing commas')]
    public function testEmptyCategoryNamesAreSkipped(): void
    {
        $catId = Uuid::randomHex();
        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex(), categoryIds: ['Shoes' => $catId]);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-010',
            grossPrice: 59.99,
            categories: 'Shoes,',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertCount(1, $data['data']['categories']);
    }

    #[TestDox('omits categories from payload when names do not match')]
    public function testUnresolvedCategoriesAreOmitted(): void
    {
        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex(), categoryIds: []);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-007',
            grossPrice: 39.99,
            categories: 'Nonexistent',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayNotHasKey('categories', $data['data']);
    }

    #[TestDox('commits product and returns product ID when dryRun is false')]
    public function testCommitCreatesProductAndReturnsId(): void
    {
        $productRepo = static::createStub(EntityRepository::class);

        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex(), productRepo: $productRepo);
        $output = ($tool)(
            name: 'Commit Product',
            productNumber: 'SW-TEST-008',
            grossPrice: 49.99,
            dryRun: false,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertArrayHasKey('productId', $data['data']);
        static::assertSame('SW-TEST-008', $data['data']['productNumber']);
    }

    #[TestDox('returns error when upsert throws on commit')]
    public function testCommitUpsertExceptionReturnsError(): void
    {
        $productRepo = static::createStub(EntityRepository::class);
        $productRepo->method('upsert')->willThrowException(new \RuntimeException('DB write failed'));

        $tool = $this->createTool(Uuid::randomHex(), Uuid::randomHex(), productRepo: $productRepo);
        $output = ($tool)(
            name: 'Failing Product',
            productNumber: 'SW-TEST-009',
            grossPrice: 49.99,
            dryRun: false,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('DB write failed', $data['error']);
    }

    #[TestDox('returns error when tax rate is not found')]
    public function testMissingTaxRateReturnsError(): void
    {
        $tool = $this->createTool(null, Uuid::randomHex());
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-002',
            grossPrice: 49.99,
            taxRate: 7,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('7.00%', $data['error']);
    }

    #[TestDox('returns error when currency ISO code is not found')]
    public function testMissingCurrencyReturnsError(): void
    {
        $tool = $this->createTool(Uuid::randomHex(), null);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-003',
            grossPrice: 49.99,
            currencyCode: 'USD',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('USD', $data['error']);
    }

    #[TestDox('returns error when ACL privileges are missing')]
    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new ProductCreateTool(static::createStub(DefinitionInstanceRegistry::class), $contextProvider);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-004',
            grossPrice: 29.99,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
    }

    /**
     * @param array<string, string> $categoryIds map of category name => ID
     * @param EntityRepository<EntityCollection<Entity>>|null $productRepo
     */
    private function createTool(
        ?string $taxId,
        ?string $currencyId,
        array $categoryIds = [],
        ?EntityRepository $productRepo = null,
    ): ProductCreateTool {
        $context = Context::createDefaultContext();

        $taxResult = new IdSearchResult(
            $taxId !== null ? 1 : 0,
            $taxId !== null ? [$taxId => ['primaryKey' => $taxId, 'data' => []]] : [],
            new Criteria(),
            $context,
        );

        $taxRepo = static::createStub(EntityRepository::class);
        $taxRepo->method('searchIds')->willReturn($taxResult);

        $currencyResult = new IdSearchResult(
            $currencyId !== null ? 1 : 0,
            $currencyId !== null ? [$currencyId => ['primaryKey' => $currencyId, 'data' => []]] : [],
            new Criteria(),
            $context,
        );

        $currencyRepo = static::createStub(EntityRepository::class);
        $currencyRepo->method('searchIds')->willReturn($currencyResult);

        $categoryRepo = static::createStub(EntityRepository::class);
        if ($categoryIds !== []) {
            $responses = [];
            foreach ($categoryIds as $catId) {
                $responses[] = new IdSearchResult(
                    1,
                    [$catId => ['primaryKey' => $catId, 'data' => []]],
                    new Criteria(),
                    $context,
                );
            }
            $categoryRepo->method('searchIds')->willReturnOnConsecutiveCalls(...$responses);
        } else {
            $categoryRepo->method('searchIds')->willReturn(
                new IdSearchResult(0, [], new Criteria(), $context),
            );
        }

        $productRepo ??= static::createStub(EntityRepository::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnCallback(
            function (string $entity) use ($taxRepo, $currencyRepo, $categoryRepo, $productRepo) {
                return match ($entity) {
                    'tax' => $taxRepo,
                    'currency' => $currencyRepo,
                    'category' => $categoryRepo,
                    'product' => $productRepo,
                    default => $this->createStub(EntityRepository::class),
                };
            }
        );

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new ProductCreateTool($registry, $contextProvider);
    }
}
