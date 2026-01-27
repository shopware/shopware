<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:url:verify',
    description: 'Check the status of the app URL and force verification',
)]
#[Package('framework')]
class AppUrlVerifyCommand extends Command
{
    public function __construct(
        private readonly ShopIdProvider $shopIdProvider,
        private readonly AppUrlVerifier $appUrlVerifier,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shopId = $this->shopIdProvider->getShopIdWithoutVerification();

        $this->appUrlVerifier->forceVerify($shopId);

        /** @var \Symfony\Component\Console\Application $application */
        $application = $this->getApplication();
        $command = $application->find('app:url:status');
        $command->run(new ArrayInput(['command' => 'app:url:status']), $output);

        return Command::SUCCESS;
    }
}
