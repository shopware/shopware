<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sales-channel:update:domain',
    description: 'Updates a sales channel domain',
)]
#[Package('core')]
class SalesChannelUpdateDomainCommand extends Command
{
    /**
     * @internal
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
        $context = Context::createDefaultContext();
        $domains = $this->salesChannelDomainRepository->search(new Criteria(), $context);

        $host = $input->getArgument('domain');
        $previousHost = $input->getOption('previous-domain');

        $payload = [];
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            // Ignore default headless
            if (str_starts_with($domain->getUrl(), 'default.headless')) {
                continue;
            }

            if ($previousHost && parse_url($domain->getUrl(), \PHP_URL_HOST) !== $previousHost) {
                continue;
            }

            $newDomain = $this->replaceDomain($domain->getUrl(), $host);

            $payload[] = [
                'id' => $domain->getId(),
                'url' => $newDomain,
            ];
        }

        $this->salesChannelDomainRepository->update($payload, $context);

        return self::SUCCESS;
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

    private function buildUrl(array $parts): string
    {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '')
            . ((isset($parts['user']) || isset($parts['host'])) ? '//' : '')
            . (isset($parts['user']) ? (string) ($parts['user']) : '')
            . (isset($parts['pass']) ? ":{$parts['pass']}" : '')
            . (isset($parts['user']) ? '@' : '')
            . (isset($parts['host']) ? "{$parts['host']}" : '')
            . (isset($parts['port']) ? ":{$parts['port']}" : '')
            . (isset($parts['path']) ? "{$parts['path']}" : '')
            . (isset($parts['query']) ? "?{$parts['query']}" : '')
            . (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }
}
