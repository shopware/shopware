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
 * `bin/console ucp:keys:create --sales-channel=<id> [--algorithm=ES256] [--no-rotate]`
 *
 * @internal
 */
#[AsCommand(name: 'ucp:keys:create', description: 'Generate a new UCP signing key for a sales channel (rotates active key by default)')]
#[Package('framework')]
class UcpKeysCreateCommand extends Command
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
        $this->addOption('no-rotate', null, InputOption::VALUE_NONE, 'Do not retire the currently active key');
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
        $rotate = !$input->getOption('no-rotate');

        $key = $this->signingKeyProvider->create($salesChannelId, $algorithm, Context::createCLIContext(), $rotate);

        $io->success(\sprintf(
            'Created UCP signing key kid=%s algorithm=%s rotate=%s',
            $key->getKid(),
            $key->getAlgorithm(),
            $rotate ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
