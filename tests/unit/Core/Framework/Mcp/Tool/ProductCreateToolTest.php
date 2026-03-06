<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);
        $connection = $this->createMock(Connection::class);

        $tool = new ProductCreateTool($registry, $contextProvider, $connection);
        $output = ($tool)(
            name: 'Test Product',
            productNumber: 'SW-TEST-004',
            grossPrice: 29.99,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
    }

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

    private function createTool(?string $taxId, ?string $currencyId): ProductCreateTool
    {
        $context = Context::createDefaultContext();

        $taxResult = new IdSearchResult(
            $taxId !== null ? 1 : 0,
            $taxId !== null ? [$taxId => ['primaryKey' => $taxId, 'data' => []]] : [],
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            $context,
        );

        $taxRepo = $this->createMock(EntityRepository::class);
        $taxRepo->method('searchIds')->willReturn($taxResult);

        $currencyResult = new IdSearchResult(
            $currencyId !== null ? 1 : 0,
            $currencyId !== null ? [$currencyId => ['primaryKey' => $currencyId, 'data' => []]] : [],
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            $context,
        );

        $currencyRepo = $this->createMock(EntityRepository::class);
        $currencyRepo->method('searchIds')->willReturn($currencyResult);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnCallback(function (string $entity) use ($taxRepo, $currencyRepo) {
            return match ($entity) {
                'tax' => $taxRepo,
                'currency' => $currencyRepo,
                default => $this->createMock(EntityRepository::class),
            };
        });

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $connection = $this->createMock(Connection::class);

        return new ProductCreateTool($registry, $contextProvider, $connection);
    }
}
