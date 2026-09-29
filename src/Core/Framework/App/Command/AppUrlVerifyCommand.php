<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'app:url:verify',
    description: 'Check the status of the app URL and force verification',
)]
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
        $io = new SymfonyStyle($input, $output);

        $shopId = $this->shopIdProvider->getShopId();
        $state = $this->appUrlVerifier->forceVerify($shopId);

        $this->printer->print($io, $state, true);

        return Command::SUCCESS;
    }
}
