<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
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
 * `bin/console ucp:keys:rotate --sales-channel=<id> [--algorithm=ES256]`
 *
 * Generates a new active key for the sales channel and transitions the
 * previously active key to `retiring`. Equivalent to `ucp:keys:create`
 * without `--no-rotate`; kept as a dedicated command so rotation appears
 * as an explicit operation in runbooks.
 *
 * @internal
 */
#[AsCommand(name: 'ucp:keys:rotate', description: 'Rotate the active UCP signing key for a sales channel (new key becomes active, previous one transitions to retiring)')]
#[Package('framework')]
class UcpKeysRotateCommand extends Command
{
    public function __construct(
        private readonly UcpSigningKeyProvider $signingKeyProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'Sales channel id (hex)');
        $this->addOption('algorithm', null, InputOption::VALUE_REQUIRED, 'ES256 (default) or ES384', UcpSigningKeyEntity::ALGORITHM_ES256);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $salesChannelId = $input->getOption('sales-channel');
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            $io->error('--sales-channel is required');

            return self::INVALID;
        }

        $algorithm = (string) $input->getOption('algorithm');
        $context = Context::createCLIContext();

        $previousActive = $this->signingKeyProvider->getActive($salesChannelId, $context);
        $created = $this->signingKeyProvider->create($salesChannelId, $algorithm, $context, true);

        $io->success(\sprintf(
            'Rotated UCP signing key for sales channel %s: new active kid=%s (algorithm=%s)%s',
            $salesChannelId,
            $created->getKid(),
            $created->getAlgorithm(),
            $previousActive !== null
                ? \sprintf(', previous active kid=%s now retiring', $previousActive->getKid())
                : ' (no previous active key)'
        ));

        return self::SUCCESS;
    }
}
