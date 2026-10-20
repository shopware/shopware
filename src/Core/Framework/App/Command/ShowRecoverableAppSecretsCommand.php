<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[AsCommand(
    name: 'app:secret:recoverable',
    description: 'Print the live and retained secrets of an app, newest first. The output contains live app secrets and is sensitive.',
)]
class ShowRecoverableAppSecretsCommand extends Command
{
    private const LIVE_RECORD_SHOP_ID = 'current';
    private const LIVE_RECORD_EXPIRY = '-';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppRetainedSecretGateway $retainedSecrets,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the app');
        $this->addOption('raw', null, InputOption::VALUE_NONE, 'Print only the secrets, one per line, for piping into "app:secret:rotate <name> --adopt-secrets"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        $rows = $this->collectRows($name);
        if ($rows === []) {
            $io->error(\sprintf('No secrets found for app "%s": it is not installed and none were retained.', $name));

            return self::FAILURE;
        }

        if ($input->getOption('raw')) {
            foreach ($rows as $row) {
                $output->writeln($row[2], OutputInterface::OUTPUT_RAW);
            }

            return self::SUCCESS;
        }

        $io->warning('This output contains live app secrets. Handle it as a credential.');
        $io->table(['ShopId', 'Expires', 'Secret'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function collectRows(string $name): array
    {
        $rows = [];

        $app = $this->appRepository->search((new Criteria())->addFilter(new EqualsFilter('name', $name)), Context::createCLIContext())->getEntities()->first();
        if ($app !== null) {
            foreach ([...($app->getUnconfirmedAppSecrets() ?? []), $app->getAppSecret()] as $secret) {
                if ($secret !== null) {
                    $rows[] = [self::LIVE_RECORD_SHOP_ID, self::LIVE_RECORD_EXPIRY, $secret];
                }
            }
        }

        foreach ($this->retainedSecrets->findValid($name) as $retained) {
            $rows[] = [$retained['shopId'], $retained['expiresAt']->format(\DATE_ATOM), $retained['secret']];
        }

        return $rows;
    }
}
