<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-product-create', description: 'Create a product with human-readable inputs. Automatically resolves tax rate to tax ID, currency ISO code to currency ID, category names to category IDs, and builds the nested price structure. Defaults to dryRun=true. Returns the generated payload in dryRun mode, or the created product ID on commit.')]
#[Package('framework')]
class ProductCreateTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        string $name,
        string $productNumber,
        float $grossPrice,
        float $taxRate = 19,
        string $currencyCode = 'EUR',
        int $stock = 0,
        string $description = '',
        string $categories = '',
        bool $active = true,
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        foreach (['product:create', 'product:read', 'tax:read', 'currency:read'] as $privilege) {
            if (!$context->isAllowed($privilege)) {
                return $this->error(\sprintf('Missing privilege: %s', $privilege));
            }
        }

        $taxId = $this->resolveTaxId($taxRate, $context);

        if ($taxId === null) {
            return $this->error(\sprintf('No tax found with rate %.2f%%. Create the tax first or use a different rate.', $taxRate));
        }

        $currencyId = $this->resolveCurrencyId($currencyCode, $context);

        if ($currencyId === null) {
            return $this->error(\sprintf('No currency found with ISO code "%s".', $currencyCode));
        }

        $netPrice = round($grossPrice / (1 + $taxRate / 100), 4);

        $payload = [
            'id' => Uuid::randomHex(),
            'name' => $name,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'active' => $active,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => $currencyId,
                'gross' => $grossPrice,
                'net' => $netPrice,
                'linked' => true,
            ]],
        ];

        if ($description !== '') {
            $payload['description'] = $description;
        }

        if ($categories !== '') {
            $categoryIds = $this->resolveCategoryIds($categories, $context);

            if ($categoryIds !== []) {
                $payload['categories'] = array_map(fn (string $id) => ['id' => $id], $categoryIds);
            }
        }

        if ($dryRun) {
            return $this->success($payload, ['dryRun' => true]);
        }

        $repository = $this->registry->getRepository('product');

        $this->connection->beginTransaction();

        try {
            $repository->upsert([$payload], $context);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            return $this->error($e->getMessage());
        }

        return $this->success(['productId' => $payload['id'], 'productNumber' => $productNumber], ['dryRun' => false]);
    }

    private function resolveTaxId(float $taxRate, \Shopware\Core\Framework\Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', $taxRate));
        $criteria->setLimit(1);

        $result = $this->registry->getRepository('tax')->searchIds($criteria, $context);

        return $result->firstId();
    }

    private function resolveCurrencyId(string $isoCode, \Shopware\Core\Framework\Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $isoCode));
        $criteria->setLimit(1);

        $result = $this->registry->getRepository('currency')->searchIds($criteria, $context);

        return $result->firstId();
    }

    /**
     * @return list<string>
     */
    private function resolveCategoryIds(string $categories, \Shopware\Core\Framework\Context $context): array
    {
        $names = array_map('trim', explode(',', $categories));
        $repository = $this->registry->getRepository('category');
        $ids = [];

        foreach ($names as $categoryName) {
            if ($categoryName === '') {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new ContainsFilter('name', $categoryName));
            $criteria->setLimit(1);

            $id = $repository->searchIds($criteria, $context)->firstId();

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
