<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StoreLoginCommand extends Command
{
    public static $defaultName = 'store:login';

    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(
        StoreClient $storeClient,
        EntityRepositoryInterface $userRepository,
        SystemConfigService $configService
    ) {
        $this->storeClient = $storeClient;
        $this->userRepository = $userRepository;
        $this->configService = $configService;

        parent::__construct();
    }

    /**
     * @deprecated tag:v6.5.0 option language will be removed
     */
    protected function configure(): void
    {
        $this->addOption('shopwareId', 'i', InputOption::VALUE_REQUIRED, 'Shopware ID')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User')
            ->addOption('host', 'g', InputOption::VALUE_OPTIONAL, 'License host')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        $host = $input->getOption('host');
        if (!empty($host)) {
            $this->configService->set('core.store.licenseHost', $host);
        }

        $shopwareId = $input->getOption('shopwareId');
        $password = $input->getOption('password');
        $user = $input->getOption('user');

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

        return Command::SUCCESS;
    }
}
