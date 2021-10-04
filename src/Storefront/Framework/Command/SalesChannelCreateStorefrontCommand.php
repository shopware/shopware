<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SalesChannelCreateStorefrontCommand extends SalesChannelCreateCommand
{
    protected static $defaultName = 'sales-channel:create:storefront';

    private EntityRepositoryInterface $snippetSetRepository;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $snippetSetRepository,
        EntityRepositoryInterface $categoryRepository,
        SalesChannelCreator $salesChannelCreator
    ) {
        parent::__construct(
            $paymentMethodRepository,
            $shippingMethodRepository,
            $countryRepository,
            $snippetSetRepository,
            $categoryRepository,
            $salesChannelCreator
        );

        $this->snippetSetRepository = $snippetSetRepository;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'App URL for storefront')
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Default snippet set')
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
            'navigationCategoryDepth' => 3,
            'name' => $input->getOption('name') ?? 'Storefront',
        ];
    }

    /**
     * @deprecated tag:v6.5.0 - Will be made private when parent implementation is removed
     */
    protected function getSnippetSetId(): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', 'en-GB'));

        /** @var string|null $id */
        $id = $this->snippetSetRepository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;

        if ($id === null) {
            throw new \InvalidArgumentException('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        }

        return $id;
    }
}
