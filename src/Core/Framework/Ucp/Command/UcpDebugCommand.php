<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSalesChannelConfigCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\UcpVersion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * `bin/console debug:ucp` — prints registered capabilities, payment handlers,
 * per-Sales-Channel config state, and the current feature flag value.
 *
 * @internal
 */
#[AsCommand(name: 'debug:ucp', description: 'Prints UCP capability registry, payment handlers, and per-Sales-Channel config state')]
#[Package('framework')]
class UcpDebugCommand extends Command
{
    /**
     * @param EntityRepository<UcpSalesChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly CapabilityRegistry $capabilityRegistry,
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EntityRepository $configRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('UCP — Universal Commerce Protocol');
        $io->writeln(\sprintf('  Feature flag UCP_SERVER : <fg=cyan>%s</>', Feature::isActive('UCP_SERVER') ? 'enabled' : 'disabled'));
        $io->writeln(\sprintf('  Current protocol version: <fg=cyan>%s</>', UcpVersion::CURRENT));
        $io->writeln(\sprintf('  Historical versions    : %s', $this->formatList(UcpVersion::HISTORICAL)));
        $io->newLine();

        $io->section('Capabilities');
        $capRows = [];
        foreach ($this->capabilityRegistry->all() as $name => $capability) {
            $extends = $capability->getExtends();
            $extendsStr = $extends === null ? '-' : (\is_array($extends) ? implode(', ', $extends) : $extends);
            $capRows[] = [$name, $capability->getVersion(), $extendsStr];
        }
        $io->table(['Name', 'Version', 'Extends'], $capRows);

        $io->section('Payment Handlers');
        $handlerRows = [];
        foreach ($this->paymentHandlerRegistry->all() as $nameId => $handler) {
            $handlerRows[] = [$nameId, $handler::class];
        }
        if ($handlerRows === []) {
            $io->writeln('  <comment>(no payment handlers registered)</comment>');
        } else {
            $io->table(['Name ID', 'Implementation'], $handlerRows);
        }

        $io->section('Sales Channel Configurations');
        $configs = $this->configRepository->search(new Criteria(), Context::createCLIContext());
        $cfgRows = [];
        foreach ($configs as $config) {
            \assert($config instanceof UcpSalesChannelConfigEntity);
            $cfgRows[] = [
                $config->getSalesChannelId(),
                $config->isActive() ? 'yes' : 'no',
                $config->getUcpVersion(),
                \count($config->getEnabledCapabilities()),
                implode(',', $config->getEnabledTransports()),
            ];
        }
        if ($cfgRows === []) {
            $io->writeln('  <comment>(no sales channels configured for UCP)</comment>');
        } else {
            $io->table(['Sales Channel ID', 'Active', 'Version', '# Caps', 'Transports'], $cfgRows);
        }

        return self::SUCCESS;
    }

    /**
     * @param list<string> $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '<comment>(none)</comment>' : implode(', ', $items);
    }
}
