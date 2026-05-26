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
 * `bin/console ucp:keys:retire --sales-channel=<id> --kid=<kid>`
 *
 * Transitions a key into `retiring`. The key remains usable for inbound
 * signature verification during the grace window
 * ({@see UcpSigningKeyProvider::RETIREMENT_GRACE_PERIOD_SECONDS}) before
 * the scheduled retirement task transitions it to `retired`.
 *
 * @internal
 */
#[AsCommand(name: 'ucp:keys:retire', description: 'Retire a UCP signing key. The key stays usable for inbound verification during the grace window before final retirement.')]
#[Package('framework')]
class UcpKeysRetireCommand extends Command
{
    public function __construct(
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'Sales channel id (hex)');
        $this->addOption('kid', null, InputOption::VALUE_REQUIRED, 'Key id of the signing key to retire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $salesChannelId = $input->getOption('sales-channel');
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            $io->error('--sales-channel is required');

            return self::INVALID;
        }

        $kid = $input->getOption('kid');
        if (!\is_string($kid) || $kid === '') {
            $io->error('--kid is required');

            return self::INVALID;
        }

        $key = $this->signingKeyProvider->retire($salesChannelId, $kid, Context::createCLIContext());

        $io->success(\sprintf(
            'Retired UCP signing key kid=%s (status=%s, retiring_at=%s) for sales channel %s',
            $key->getKid(),
            $key->getStatus(),
            $key->getRetiringAt()?->format(\DateTimeInterface::ATOM) ?? '-',
            $salesChannelId
        ));

        return self::SUCCESS;
    }
}
