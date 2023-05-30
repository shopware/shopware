<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'es:admin:test',
    description: 'Allows you to test the admin search index',
)]
#[Package('system-settings')]
final class ElasticsearchAdminTestCommand extends Command
{
    private SymfonyStyle $io;

    /**
     * @internal
     */
    public function __construct(private readonly AdminSearcher $searcher)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $term = $input->getArgument('term');
        $entities = [
            CmsPageDefinition::ENTITY_NAME,
            CustomerDefinition::ENTITY_NAME,
            CustomerGroupDefinition::ENTITY_NAME,
            LandingPageDefinition::ENTITY_NAME,
            ProductManufacturerDefinition::ENTITY_NAME,
            MediaDefinition::ENTITY_NAME,
            OrderDefinition::ENTITY_NAME,
            PaymentMethodDefinition::ENTITY_NAME,
            ProductDefinition::ENTITY_NAME,
            PromotionDefinition::ENTITY_NAME,
            PropertyGroupDefinition::ENTITY_NAME,
            SalesChannelDefinition::ENTITY_NAME,
            ShippingMethodDefinition::ENTITY_NAME,
        ];

        $result = $this->searcher->search($term, $entities, Context::createDefaultContext());

        $rows = [];
        foreach ($result as $data) {
            $rows[] = [$data['index'], $data['indexer'], $data['total']];
        }

        $this->io->table(['Index', 'Indexer', 'total'], $rows);

        return self::SUCCESS;
    }
}
