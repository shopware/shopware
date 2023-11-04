<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @internal
 */
#[AsCommand(
    name: 'store:login',
    description: 'Login to the store',
)]
#[Package('merchant-services')]
class StoreLoginCommand extends Command
{
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $configService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('shopwareId', 'i', InputOption::VALUE_REQUIRED, 'Shopware ID')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User')
            ->addOption('host', 'g', InputOption::VALUE_OPTIONAL, 'License host')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $host = $input->getOption('host');
        if (!empty($host)) {
            $this->configService->set('core.store.licenseHost', $host);
        }

        $shopwareId = $input->getOption('shopwareId');
        $password = $input->getOption('password');
        $user = $input->getOption('user');

        if (!$password) {
            $passwordQuestion = new Question('Enter password');
            $passwordQuestion->setValidator(static function ($value): string {
                if ($value === null || trim($value) === '') {
                    throw new \RuntimeException('The password cannot be empty');
                }

                return $value;
            });
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $user));

        $userId = $this->userRepository->searchIds($criteria, $context)->firstId();

        if ($userId === null) {
            throw new \RuntimeException('User not found');
        }

        $userContext = new Context(new AdminApiSource($userId));

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        try {
            $this->storeClient->loginWithShopwareId($shopwareId, $password, $userContext);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $io->success('Successfully logged in.');

        return (int) Command::SUCCESS;
    }
}
