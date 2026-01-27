<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:url:status',
    description: 'Check the status of the app URL',
)]
#[Package('framework')]
class AppUrlVerificationStatusCommand extends Command
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
        $io = new ShopwareStyle($input, $output);

        $state = $this->appUrlVerifier->getCurrentState();

        if ($state === null) {
            $io->warning('No verification status found. Run "app:url:verify".');

            return Command::SUCCESS;
        }

        $io->title('App URL Verification Status');

        $shopId = $this->shopIdProvider->getShopIdWithoutVerification();

        $io->writeln(\sprintf("<info>APP URL: %s</info>\n", $shopId->getFingerprint(AppUrl::IDENTIFIER)));

        $io->definitionList(
            ['Result' => match ($state->status) {
                VerificationStatus::PASS => '<info>OK</info>',
                VerificationStatus::SOFT_FAIL => '<comment>SOFT FAIL</comment> - please try again',
                VerificationStatus::HARD_FAIL => '<error>HARD FAIL</error> - APP_URL is incorrect or not reachable',
            }],
            ['Info' => $state->info ?? 'No additional information available'],
            ['Tries' => $state->numTries],
            ['Checked at' => $state->at->format('Y-m-d H:i:s T')]
        );

        $io->note('When a hard fail occurs, app communication will be disabled. When a soft fail occurs more than 3 times in a 15 minute period, it will be converted to a hard fail and app communication will also be disabled.');

        return Command::SUCCESS;
    }
}
