<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog\Command;

use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Answers a data subject request: prints every decision recorded under a consent id,
 * together with the banner configurations those decisions were made on.
 *
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'cookie:consent:show',
    description: 'Show the cookie consent decisions recorded under a consent id',
)]
class ShowCookieConsentCommand extends Command
{
    public function __construct(private readonly AbstractCookieConsentLogStorage $storage)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('consent-id', InputArgument::REQUIRED, 'The consent id the visitor\'s browser holds in the "cookie-consent-id" cookie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consentId = (string) $input->getArgument('consent-id');
        $decisions = $this->storage->findByConsentId($consentId);

        $configurations = [];
        foreach ($decisions as $decision) {
            $configurations[$decision->configHash] ??= $this->storage->findSnapshot($decision->configHash);
        }

        $output->writeln((string) json_encode([
            'consentId' => $consentId,
            'decisions' => $decisions,
            'configurations' => $configurations,
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        if ($decisions === []) {
            (new ShopwareStyle($input, $output))->getErrorStyle()->warning(\sprintf('No consent decisions are recorded for "%s"', $consentId));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
