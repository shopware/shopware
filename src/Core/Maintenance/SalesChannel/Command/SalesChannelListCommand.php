<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'sales-channel:list',
    description: 'Lists all sales channels',
)]
#[Package('core')]
class SalesChannelListCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $salesChannelRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output',
            '0',
            InputOption::VALUE_OPTIONAL,
            'Output mode',
            'table'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = [
            'id',
            'Name',
            'Active',
            'Maintenance',
            'Default Language',
            'Languages',
            'Default Currency',
            'Currencies',
            'Domains',
        ];

        $criteria = new Criteria();
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains']);
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $data = [];
        foreach ($salesChannels as $salesChannel) {
            /** @var LanguageEntity $language */
            $language = $salesChannel->getLanguage();
            /** @var LanguageCollection $languages */
            $languages = $salesChannel->getLanguages();
            /** @var CurrencyEntity $currency */
            $currency = $salesChannel->getCurrency();
            /** @var CurrencyCollection $currencies */
            $currencies = $salesChannel->getCurrencies();
            /** @var SalesChannelDomainCollection $domains */
            $domains = $salesChannel->getDomains();

            $data[] = [
                $salesChannel->getId(),
                $salesChannel->getName(),
                $salesChannel->getActive() ? 'active' : 'inactive',
                $salesChannel->isMaintenance() ? 'on' : 'off',
                $language->getName(),
                $languages->map(fn (LanguageEntity $language) => $language->getName()),
                $currency->getName(),
                $currencies->map(fn (CurrencyEntity $currency) => $currency->getName()),
                $domains->map(fn (SalesChannelDomainEntity $domain) => $domain->getUrl()),
            ];
        }

        if ($input->getOption('output') === 'json') {
            return $this->renderJson($output, $headers, $data);
        }

        return $this->renderTable($output, $headers, $data);
    }

    private function renderJson(OutputInterface $output, array $headers, array $data): int
    {
        $json = [];

        foreach ($data as $row) {
            $jsonItem = [];
            foreach ($row as $item => $value) {
                $jsonItem[mb_strtolower((string) ($headers[$item] ?? $item))] = $value;
            }
            $json[] = $jsonItem;
        }

        $encoded = json_encode($json, \JSON_THROW_ON_ERROR);

        $output->write($encoded);

        return self::SUCCESS;
    }

    private function renderTable(OutputInterface $output, array $headers, array $data): int
    {
        $table = new Table($output);
        $table->setHeaders($headers);

        // Normalize data
        foreach ($data as $rowKey => $row) {
            foreach ($row as $columnKey => $column) {
                if (\is_array($column)) {
                    $data[$rowKey][$columnKey] = implode(', ', $column);
                }
            }
        }

        $table->addRows($data);

        $table->render();

        return self::SUCCESS;
    }
}
