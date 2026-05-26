<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Conformance\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Seeds the official UCP conformance "flower_shop" fixture into Shopware.
 *
 * This command is registered ONLY in non-production environments (dev/test).
 * It exists to set up the deterministic catalog the upstream Python
 * conformance suite (`Universal-Commerce-Protocol/conformance`) expects, and
 * is invoked from the `ucp-conformance` CI workflow before the suite runs.
 *
 * @internal
 */
#[AsCommand(name: 'ucp:conformance:seed', description: 'Seed UCP conformance flower_shop products into the default sales channel (non-prod only)')]
#[Package('framework')]
class UcpConformanceSeedCommand extends Command
{
    private const USD_CURRENCY_ID = '0f7f1f52c9f54b2ab34f0f0000000001';

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $currencyRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly Connection $connection,
        private readonly string $environment = 'prod',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'Sales channel id (hex). Defaults to first sales channel.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment === 'prod') {
            $io->error('ucp:conformance:seed is a non-production fixture command. Refusing to run with APP_ENV=prod.');

            return self::FAILURE;
        }
        $context = Context::createCLIContext();

        $salesChannelId = $input->getOption('sales-channel');
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel LIMIT 1');
        }
        if (!\is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            $io->error('No sales channel found for conformance seeding.');

            return self::FAILURE;
        }

        $taxId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM tax ORDER BY tax_rate ASC LIMIT 1');
        if (!\is_string($taxId) || !Uuid::isValid($taxId)) {
            $io->error('No tax row found for product seeding.');

            return self::FAILURE;
        }

        $usdCurrencyId = $this->ensureUsdCurrency($context);
        $this->salesChannelRepository->update([[
            'id' => $salesChannelId,
            'currencyId' => $usdCurrencyId,
            'currencies' => [['id' => $usdCurrencyId]],
        ]], $context);
        $this->connection->executeStatement(
            'UPDATE sales_channel_domain SET currency_id = ? WHERE sales_channel_id = ?',
            [Uuid::fromHexToBytes($usdCurrencyId), Uuid::fromHexToBytes($salesChannelId)]
        );
        $this->connection->executeStatement(
            'DELETE FROM product_visibility WHERE product_id IN (?, ?) AND sales_channel_id = ?',
            [
                Uuid::fromHexToBytes($this->productId('bouquet_roses')),
                Uuid::fromHexToBytes($this->productId('gardenias')),
                Uuid::fromHexToBytes($salesChannelId),
            ]
        );

        $this->productRepository->upsert([
            $this->productPayload('bouquet_roses', 'Red Rose', 35.00, 100, $taxId, $salesChannelId, $usdCurrencyId),
            $this->productPayload('gardenias', 'Gardenias', 25.00, 0, $taxId, $salesChannelId, $usdCurrencyId),
        ], $context);

        $io->success('Seeded UCP conformance flower_shop products (bouquet_roses, gardenias) and set sales channel currency to USD.');

        return self::SUCCESS;
    }

    private function ensureUsdCurrency(Context $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', 'USD'))->setLimit(1);
        $existing = $this->currencyRepository->searchIds($criteria, $context)->firstId();
        if (\is_string($existing) && Uuid::isValid($existing) && $existing !== self::USD_CURRENCY_ID) {
            return $existing;
        }

        $this->currencyRepository->upsert([[
            'id' => self::USD_CURRENCY_ID,
            'isoCode' => 'USD',
            'factor' => 1.0,
            'symbol' => '$',
            'shortName' => 'USD',
            'name' => 'US Dollar',
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        ]], $context);

        return self::USD_CURRENCY_ID;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $productNumber, string $name, float $gross, int $stock, string $taxId, string $salesChannelId, string $usdCurrencyId): array
    {
        $net = round($gross / 1.19, 2);

        return [
            'id' => $this->productId($productNumber),
            'productNumber' => $productNumber,
            'name' => $name,
            'active' => true,
            'stock' => $stock,
            'taxId' => $taxId,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $net, 'linked' => false],
                ['currencyId' => $usdCurrencyId, 'gross' => $gross, 'net' => $net, 'linked' => false],
            ],
            'visibilities' => [[
                'id' => substr(Hasher::hash('ucp-conformance-visibility-' . $productNumber . '-' . $salesChannelId), 0, 32),
                'salesChannelId' => $salesChannelId,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
        ];
    }

    private function productId(string $productNumber): string
    {
        return substr(Hasher::hash('ucp-conformance-' . $productNumber), 0, 32);
    }
}
