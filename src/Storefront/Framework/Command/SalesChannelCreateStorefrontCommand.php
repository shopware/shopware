<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Command\SalesChannelCreateCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SalesChannelCreateStorefrontCommand extends SalesChannelCreateCommand
{
    protected static $defaultName = 'sales-channel:create:storefront';

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $snippetSetRepository,
        EntityRepositoryInterface $categoryRepository
    ) {
        parent::__construct(
            $definitionRegistry,
            $salesChannelRepository,
            $paymentMethodRepository,
            $shippingMethodRepository,
            $countryRepository,
            $snippetSetRepository,
            $categoryRepository
        );
        $this->categoryRepository = $categoryRepository;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'App URL for storefront')
        ;
    }

    protected function getTypeId(): string
    {
        return Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
    }

    protected function getSalesChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        $snippetSet = $input->getOption('snippetSetId') ?? $this->getSnippetSetId();

        return [
            'domains' => [
                [
                    'url' => $input->getOption('url'),
                    'languageId' => $input->getOption('languageId'),
                    'snippetSetId' => $snippetSet,
                    'currencyId' => $input->getOption('currencyId'),
                ],
            ],
            'navigationCategoryId' => $this->getRootCategoryId(),
            'name' => $input->getOption('name') ?? 'Storefront',
        ];
    }

    private function getRootCategoryId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        $categories = $this->categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        return array_shift($categories);
    }
}
