<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type SecretRow array{app: string, shopId: string, expires: string, secret: string, kept: bool}
 */
#[Package('framework')]
#[AsCommand(
    name: 'app:secret:recover',
    description: 'Hand app secrets from a shop copy to the shop it took over, so that shop can register with its apps again.',
)]
class RecoverAppSecretsCommand extends Command
{
    private const CURRENT_SHOP_ID = 'current';
    private const NO_EXPIRY = '-';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppRetainedSecretGateway $retainedSecrets,
        private readonly AppSecretRotationService $rotationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the app');
        $this->addOption('list', null, InputOption::VALUE_NONE, 'On the copy: print every secret it holds, with ShopId and expiry. The output contains live app secrets.');
        $this->addOption('start', null, InputOption::VALUE_NONE, 'On the copy: print the secrets the original shop needs, one per line with the app name in front. The output contains live app secrets.');
        $this->addOption('finish', null, InputOption::VALUE_NONE, 'On the original shop: read the lines printed by --start from standard input and register with each app again');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'With --list or --start: all apps instead of one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $steps = array_filter(['list', 'start', 'finish'], static fn (string $step): bool => (bool) $input->getOption($step));
        if (\count($steps) !== 1) {
            $io->error('Pass exactly one of --list, --start or --finish.');

            return self::FAILURE;
        }

        $name = $input->getArgument('name');
        $all = (bool) $input->getOption('all');

        if (\in_array('finish', $steps, true)) {
            if ($name !== null || $all) {
                $io->error('--finish reads the app names from standard input and takes no app name or --all.');

                return self::FAILURE;
            }

            return $this->finish($input, $io);
        }

        if (($name !== null) === $all) {
            $io->error('Pass an app name or --all.');

            return self::FAILURE;
        }

        $rows = $this->collectRows($all ? null : (string) $name);
        if ($rows === []) {
            $io->error('No secrets found.');

            return self::FAILURE;
        }

        if (\in_array('list', $steps, true)) {
            $this->printTable($rows, $io);
        } else {
            $this->printHandover($rows, $output);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<SecretRow>
     */
    private function collectRows(?string $appName): array
    {
        return [...$this->currentRows($appName), ...$this->keptRows($appName)];
    }

    /**
     * @return list<SecretRow>
     */
    private function currentRows(?string $appName): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('appSecret', null));
        if ($appName !== null) {
            $criteria->addFilter(new EqualsFilter('name', $appName));
        }

        $rows = [];
        foreach ($this->appRepository->search($criteria, Context::createCLIContext())->getEntities() as $app) {
            foreach ([...($app->getUnconfirmedAppSecrets() ?? []), $app->getAppSecret()] as $secret) {
                if ($secret !== null) {
                    $rows[] = ['app' => $app->getName(), 'shopId' => self::CURRENT_SHOP_ID, 'expires' => self::NO_EXPIRY, 'secret' => $secret, 'kept' => false];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<SecretRow>
     */
    private function keptRows(?string $appName): array
    {
        return array_map(static fn (array $kept): array => [
            'app' => $kept['appName'],
            'shopId' => $kept['shopId'],
            'expires' => $kept['expiresAt']->format(\DATE_ATOM),
            'secret' => $kept['secret'],
            'kept' => true,
        ], $this->retainedSecrets->findValid($appName));
    }

    /**
     * @param list<SecretRow> $rows
     */
    private function printTable(array $rows, SymfonyStyle $io): void
    {
        $io->warning('This output contains live app secrets. Handle it as a credential.');
        $io->table(
            ['App', 'ShopId', 'Expires', 'Secret'],
            array_map(static fn (array $row): array => [$row['app'], $row['shopId'], $row['expires'], $row['secret']], $rows)
        );
    }

    /**
     * @param list<SecretRow> $rows
     */
    private function printHandover(array $rows, OutputInterface $output): void
    {
        foreach ($this->groupByApp($rows) as $appName => $appRows) {
            $kept = array_filter($appRows, static fn (array $row): bool => $row['kept']);

            foreach ($kept !== [] ? $kept : $appRows as $row) {
                $output->writeln($appName . ' ' . $row['secret'], OutputInterface::OUTPUT_RAW);
            }
        }
    }

    /**
     * @param list<SecretRow> $rows
     *
     * @return array<string, list<SecretRow>>
     */
    private function groupByApp(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['app']][] = $row;
        }

        return $grouped;
    }

    private function finish(InputInterface $input, SymfonyStyle $io): int
    {
        $secretsByApp = $this->readHandover($input);
        if ($secretsByApp === null) {
            $io->error('Every line must hold an app name and a secret, separated by a space.');

            return self::FAILURE;
        }

        if ($secretsByApp === []) {
            $io->error('No secrets were read from standard input.');

            return self::FAILURE;
        }

        $context = Context::createCLIContext();
        $apps = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsAnyFilter('name', array_keys($secretsByApp))),
            $context
        )->getEntities();

        $failed = false;
        foreach ($secretsByApp as $appName => $secrets) {
            $app = $apps->filterByProperty('name', $appName)->first();
            if ($app === null) {
                $io->error(\sprintf('App %s is not installed on this shop.', $appName));
                $failed = true;

                continue;
            }

            try {
                $this->rotationService->rotateNow($app->getId(), $context, AppSecretRotationService::TRIGGER_CLI, $secrets);
                $io->success(\sprintf('App %s registered again.', $appName));
            } catch (\Throwable $exception) {
                $io->error(\sprintf('App %s could not register again: %s', $appName, $exception->getMessage()));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function readHandover(InputInterface $input): ?array
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;

        $secretsByApp = [];
        foreach (explode("\n", (string) stream_get_contents($stream ?? \STDIN)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if ($parts === false || \count($parts) !== 2) {
                return null;
            }

            $secretsByApp[$parts[0]][] = $parts[1];
        }

        return $secretsByApp;
    }
}
