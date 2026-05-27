<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Command;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSigningKeyCollection;
use Shopware\Core\Framework\Ucp\Security\PrivateKeyEncryptor;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * `bin/console ucp:keys:reencrypt --old-secret=<old> [--new-secret=<new>] [--sales-channel=<id>] [--dry-run]`
 *
 * Re-encrypts every stored UCP private signing key from `--old-secret` to
 * `--new-secret` (defaults to the current `APP_SECRET`). Runbook:
 *
 *   1. Rotate `APP_SECRET` in the environment / secret manager.
 *   2. Run `ucp:keys:reencrypt --old-secret=<previous APP_SECRET>`.
 *
 * Without this command a rotated `APP_SECRET` would render every UCP signing
 * key undecryptable (see {@see UcpException::keyDecryptionFailed()}). The
 * command supports `--dry-run` to verify the old secret can decrypt every row
 * before any write happens.
 *
 * @internal
 */
#[AsCommand(name: 'ucp:keys:reencrypt', description: 'Re-encrypt all UCP signing keys after APP_SECRET rotation')]
#[Package('framework')]
class UcpKeysReencryptCommand extends Command
{
    /**
     * @param EntityRepository<UcpSigningKeyCollection> $signingKeyRepository
     */
    public function __construct(
        private readonly EntityRepository $signingKeyRepository,
        private readonly PrivateKeyEncryptor $encryptor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('old-secret', null, InputOption::VALUE_REQUIRED, 'Previous APP_SECRET that the keys are currently encrypted with');
        $this->addOption('new-secret', null, InputOption::VALUE_REQUIRED, 'New APP_SECRET. Defaults to the value resolved from the environment.');
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'Optional sales channel id (hex). Without this option every UCP signing key is re-encrypted.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Decrypt every row with the old secret + encrypt with the new secret without persisting anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $oldSecret = $input->getOption('old-secret');
        if (!\is_string($oldSecret) || $oldSecret === '') {
            $io->error('--old-secret is required');

            return self::INVALID;
        }

        $newSecret = $input->getOption('new-secret');
        if (!\is_string($newSecret) || $newSecret === '') {
            $newSecret = (string) EnvironmentHelper::getVariable('APP_SECRET');
        }
        if ($newSecret === '') {
            $io->error('--new-secret is empty and APP_SECRET is not configured');

            return self::INVALID;
        }

        if ($oldSecret === $newSecret) {
            $io->warning('Old and new secret are identical — nothing to do.');

            return self::SUCCESS;
        }

        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $salesChannelId = $input->getOption('sales-channel');
        if (\is_string($salesChannelId) && $salesChannelId !== '') {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }

        $collection = $this->signingKeyRepository->search($criteria, $context)->getEntities();
        \assert($collection instanceof UcpSigningKeyCollection);

        if ($collection->count() === 0) {
            $io->warning('No UCP signing keys found' . (\is_string($salesChannelId) && $salesChannelId !== '' ? ' for sales channel ' . $salesChannelId : '') . '.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $writes = [];
        $failures = [];

        foreach ($collection as $key) {
            try {
                $newBlob = $this->encryptor->reencrypt(
                    $key->getPrivateKeyPemEncrypted(),
                    $key->getKid(),
                    $oldSecret,
                    $newSecret
                );
            } catch (UcpException $e) {
                $failures[] = [$key->getKid(), $e->getMessage()];

                continue;
            }

            $writes[] = [
                'id' => $key->getId(),
                'privateKeyPemEncrypted' => $newBlob,
            ];
        }

        if ($failures !== []) {
            $io->error(\sprintf(
                'Re-encryption failed for %d key(s). Aborting without persisting any changes.',
                \count($failures)
            ));
            $io->table(['kid', 'error'], $failures);

            return self::FAILURE;
        }

        if ($dryRun) {
            $io->success(\sprintf('[dry-run] %d UCP signing key(s) can be re-encrypted from the old secret to the new secret. No data was persisted.', \count($writes)));

            return self::SUCCESS;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $systemContext) => $this->signingKeyRepository->update($writes, $systemContext)
        );

        $io->success(\sprintf('Re-encrypted %d UCP signing key(s) with the new APP_SECRET.', \count($writes)));

        return self::SUCCESS;
    }
}
