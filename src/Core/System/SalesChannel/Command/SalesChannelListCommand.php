<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SalesChannelListCommand extends Command
{
    protected static $defaultName = 'sales-channel:list';

    private EntityRepositoryInterface $salesChannelRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;

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
            $data[] = [
                $salesChannel->getId(),
                $salesChannel->getName(),
                $salesChannel->getActive() ? 'active' : 'inactive',
                $salesChannel->isMaintenance() ? 'on' : 'off',
                $salesChannel->getLanguage()->getName(),
                $salesChannel->getLanguages()->map(function (LanguageEntity $language) {
                    return $language->getName();
                }),
                $salesChannel->getCurrency()->getName(),
                $salesChannel->getCurrencies()->map(function (CurrencyEntity $currency) {
                    return $currency->getName();
                }),
                $salesChannel->getDomains()->map(function (SalesChannelDomainEntity $domain) {
                    return $domain->getUrl();
                }),
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

        $encoded = json_encode($json);
        if ($encoded === false) {
            return self::FAILURE;
        }

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
