<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        private readonly AppUrlVerificationPrinter $printer,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $shopId = $this->shopIdProvider->getShopId();
        $this->appUrlVerifier->forceVerify($shopId, true);

        $state = $this->appUrlVerifier->getCurrentState();

        if ($state === null) {
            $io->warning('No verification state found. Check your cache configuration.');

            return Command::SUCCESS;
        }

        $this->printer->print($io, $state, true);

        return Command::SUCCESS;
    }
}
