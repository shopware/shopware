<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @final
 */
#[AsCommand(
    name: 'sales-channel:create:storefront',
    description: 'Creates a new storefront sales channel',
)]
#[Package('storefront')]
class SalesChannelCreateStorefrontCommand extends SalesChannelCreateCommand
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $snippetSetRepository,
        SalesChannelCreator $salesChannelCreator
    ) {
        parent::__construct(
            $salesChannelCreator
        );
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'App URL for storefront')
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Default snippet set')
            ->addOption('isoCode', null, InputOption::VALUE_REQUIRED, 'Snippet set iso code')
        ;
    }

    protected function getTypeId(): string
    {
        return Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
    }

    protected function getSalesChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        $snippetSet = $input->getOption('snippetSetId') ?? $this->guessSnippetSetId($input->getOption('isoCode'));

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

    private function guessSnippetSetId(?string $isoCode = 'en-GB'): string
    {
        $snippetSet = $this->getSnippetSetId($isoCode);

        if ($snippetSet === null) {
            $snippetSet = $this->getSnippetSetId();
        }

        if ($snippetSet === null) {
            throw new \InvalidArgumentException(\sprintf('Snippet set with isoCode %s cannot be found.', $isoCode));
        }

        return $snippetSet;
    }

    private function getSnippetSetId(?string $isoCode = 'en-GB'): ?string
    {
        $isoCode = $isoCode ?: 'en-GB';
        $isoCode = str_replace('_', '-', $isoCode);
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', $isoCode));

        return $this->snippetSetRepository->searchIds($criteria, Context::createCLIContext())->firstId();
    }
}
