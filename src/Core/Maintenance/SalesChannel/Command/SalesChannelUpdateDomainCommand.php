<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(
    name: 'sales-channel:update:domain',
    description: 'Updates the sales channel domain with a new domain for all or specific sales channels matching the previous domain, except headless sales channels',
)]
class SalesChannelUpdateDomainCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(private readonly EntityRepository $salesChannelDomainRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('domain', InputArgument::REQUIRED, 'Domain of the new sales channel');
        $this->addOption('previous-domain', null, InputOption::VALUE_OPTIONAL, 'Only apply to this previous domain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(MultiFilter::CONNECTION_OR, [
                new PrefixFilter('url', 'default.headless'),
            ])
        );
        $domains = $this->salesChannelDomainRepository->search($criteria, $context)->getEntities();

        $host = $input->getArgument('domain');
        $previousHost = $input->getOption('previous-domain');

        $payload = [];
        foreach ($domains as $domain) {
            if ($previousHost && parse_url($domain->getUrl(), \PHP_URL_HOST) !== $previousHost) {
                continue;
            }

            $newDomain = $this->replaceDomain($domain->getUrl(), $host);

            $payload[] = [
                'id' => $domain->getId(),
                'url' => $newDomain,
            ];
        }

        $collisions = $this->findCollisions($domains, $payload);
        if ($collisions !== []) {
            $io = new SymfonyStyle($input, $output);
            $io->error('Several domains would get the same URL, so no domain was updated. Update these domains separately, for example with sales-channel:replace:url.');
            $io->listing(array_map(
                static fn (array $collision): string => \sprintf('%s (now: %s)', $collision['url'], implode(', ', $collision['previousUrls'])),
                $collisions
            ));

            return self::FAILURE;
        }

        $this->salesChannelDomainRepository->update($payload, $context);

        return self::SUCCESS;
    }

    /**
     * The unique index on the domain URL is case-insensitive, so the URLs are compared in lower case.
     *
     * @param list<array{id: string, url: string}> $payload
     *
     * @return list<array{url: string, previousUrls: non-empty-list<string>}>
     */
    private function findCollisions(SalesChannelDomainCollection $domains, array $payload): array
    {
        $newUrls = array_column($payload, 'url', 'id');

        $domainsByUrl = [];
        foreach ($domains as $domain) {
            $url = $newUrls[$domain->getId()] ?? $domain->getUrl();
            $key = mb_strtolower($url);

            $domainsByUrl[$key]['url'] ??= $url;
            $domainsByUrl[$key]['previousUrls'][] = $domain->getUrl();
        }

        return array_values(array_filter(
            $domainsByUrl,
            static fn (array $collision): bool => \count($collision['previousUrls']) > 1
        ));
    }

    private function replaceDomain(string $url, string $newDomain): string
    {
        $components = parse_url($url);

        if ($components === false) {
            return $url;
        }

        if (\array_key_exists('host', $components)) {
            $components['host'] = $newDomain;
        }

        return $this->buildUrl($components);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts): string
    {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '')
            . ((isset($parts['user']) || isset($parts['host'])) ? '//' : '')
            . (isset($parts['user']) ? (string) ($parts['user']) : '')
            . (isset($parts['pass']) ? ':' . $parts['pass'] : '')
            . (isset($parts['user']) ? '@' : '')
            . (isset($parts['host']) ? (string) ($parts['host']) : '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (isset($parts['path']) ? (string) ($parts['path']) : '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
