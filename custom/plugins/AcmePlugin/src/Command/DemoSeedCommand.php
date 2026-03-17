<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'acme:seed',
    description: 'Seed demo products and orders for the Acme integration showcase',
)]
class DemoSeedCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $taxRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'scenario',
            null,
            InputOption::VALUE_OPTIONAL,
            'Scenario to seed: "full" (products + orders) or "products"',
            'full',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $taxId = $this->resolveTaxId($context);

        if ($taxId === null) {
            $io->error('No tax rule found in this instance. Run bin/console system:install first.');

            return Command::FAILURE;
        }

        $this->cleanupExistingProducts($context, $io);
        $this->seedProducts($taxId, $context, $io);

        $io->success('Acme seed complete.');

        return Command::SUCCESS;
    }

    private function seedProducts(string $taxId, Context $context, SymfonyStyle $io): void
    {
        $products = [];

        // Fixed UUIDs derived from product number — makes upsert idempotent on re-run.
        // Prefix 'acme' + zero-padded index hashed to a valid UUID hex.
        $baseId = 'a0000000000000000000000000000';

        // 5 valid products — all carry the required acme_sku custom field
        for ($i = 1; $i <= 5; ++$i) {
            $products[] = [
                'id' => \sprintf('%s%03d', $baseId, $i),
                'name' => \sprintf('Acme Product %d', $i),
                'productNumber' => \sprintf('ACME-%04d', $i),
                'stock' => 100,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => round(9.99 * $i, 2),
                    'net' => round(8.40 * $i, 2),
                    'linked' => true,
                ]],
                'taxId' => $taxId,
                'customFields' => ['acme_sku' => \sprintf('ACME-SKU-%04d', $i)],
            ];
        }

        // 1 intentionally incomplete product — acme_sku deliberately omitted.
        // Writing this triggers AcmeLogTriggerSubscriber to emit a warning log entry,
        // which can be found and classified by log analysis tooling.
        $products[] = [
            'id' => \sprintf('%s%03d', $baseId, 10),
            'name' => 'Acme Product (incomplete import)',
            'productNumber' => 'ACME-BROKEN-001',
            'stock' => 0,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 1.00,
                'net' => 0.84,
                'linked' => true,
            ]],
            'taxId' => $taxId,
        ];

        $this->productRepository->upsert($products, $context);

        $io->writeln(\sprintf(
            '  Seeded %d products (%d with acme_sku, 1 without — triggers validation warning in log)',
            \count($products),
            \count($products) - 1,
        ));
    }

    private function cleanupExistingProducts(Context $context, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new PrefixFilter('productNumber', 'ACME-'));
        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        if ($ids !== []) {
            $deletePayload = array_map(static fn (string $id): array => ['id' => $id], $ids);
            $this->productRepository->delete($deletePayload, $context);
            $io->writeln(\sprintf('  Removed %d existing ACME product(s) before re-seeding', \count($ids)));
        }
    }

    private function resolveTaxId(Context $context): ?string
    {
        $criteria = (new Criteria())->setLimit(1);
        $result = $this->taxRepository->searchIds($criteria, $context);

        return $result->firstId();
    }
}
