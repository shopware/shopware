<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * `bin/console ucp:keys:list --sales-channel=<id>`
 *
 * @internal
 */
#[AsCommand(name: 'ucp:keys:list', description: 'List UCP signing keys for a sales channel')]
#[Package('framework')]
class UcpKeysListCommand extends Command
{
    public function __construct(
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $salesChannelId = $input->getOption('sales-channel');
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            $io->error('--sales-channel is required');

            return self::INVALID;
        }

        $keys = $this->signingKeyProvider->getPublishable($salesChannelId, Context::createCLIContext());
        $rows = array_map(static fn ($k): array => [
            $k->getKid(),
            $k->getAlgorithm(),
            $k->getStatus(),
            $k->getActivatedAt()?->format(\DateTimeInterface::ATOM) ?? '-',
            $k->getRetiringAt()?->format(\DateTimeInterface::ATOM) ?? '-',
        ], $keys);

        $io->table(['Kid', 'Alg', 'Status', 'Activated', 'Retiring'], $rows);

        return self::SUCCESS;
    }
}
