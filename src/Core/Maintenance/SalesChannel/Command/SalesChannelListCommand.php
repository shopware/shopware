<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Console\OutputFormatTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
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
#[Package('discovery')]
class SalesChannelListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @var list<string>
     */
    private static array $headers = [
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

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        /** @deprecated tag:v6.8.0 - Use `--format` instead */
        $this->addOption(
            'output',
            '0',
            InputOption::VALUE_OPTIONAL,
            '[DEPRECATED] Use `--format` instead.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $deprecatedOutput = $input->getOption('output');
        if ($deprecatedOutput !== null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The "--output" option of the "sales-channel:list" command is deprecated and will be removed in v6.8.0. Use "--format" instead.'
            );
            $input->setOption('format', $deprecatedOutput);
        }

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains']);
        $salesChannels = $this->salesChannelRepository->search($criteria, Context::createCLIContext())->getEntities();

        $data = [];
        foreach ($salesChannels as $salesChannel) {
            $language = $salesChannel->getLanguage();
            $languages = $salesChannel->getLanguages() ?? new LanguageCollection();
            $currency = $salesChannel->getCurrency();
            $currencies = $salesChannel->getCurrencies() ?? new CurrencyCollection();
            $domains = $salesChannel->getDomains() ?? new SalesChannelDomainCollection();

            $data[] = [
                $salesChannel->getId(),
                $salesChannel->getName() ?? 'n/a',
                $salesChannel->getActive() ? 'active' : 'inactive',
                $salesChannel->isMaintenance() ? 'on' : 'off',
                $language?->getName() ?? 'n/a',
                $languages->map(static fn (LanguageEntity $language) => $language->getName()),
                $currency?->getName() ?? 'n/a',
                $currencies->map(static fn (CurrencyEntity $currency) => (string) $currency->getName()),
                $domains->map(static fn (SalesChannelDomainEntity $domain) => $domain->getUrl()),
            ];
        }

        if ($format === self::FORMAT_JSON) {
            return $this->renderJson($output, $data);
        }

        return $this->renderTable($output, $data);
    }

    /**
     * @param list<list<string|array<string, string|null>>> $data
     */
    private function renderJson(OutputInterface $output, array $data): int
    {
        $json = [];

        foreach ($data as $row) {
            $jsonItem = [];
            foreach ($row as $item => $value) {
                $jsonItem[mb_strtolower((string) (self::$headers[$item] ?? $item))] = $value;
            }
            $json[] = $jsonItem;
        }

        $encoded = json_encode($json, \JSON_THROW_ON_ERROR);

        $output->write($encoded);

        return self::SUCCESS;
    }

    /**
     * @param list<list<string|array<string, string>>> $data
     */
    private function renderTable(OutputInterface $output, array $data): int
    {
        $table = new Table($output);
        $table->setHeaders(self::$headers);

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
