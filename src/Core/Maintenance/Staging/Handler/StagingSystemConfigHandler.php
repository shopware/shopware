<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingSystemConfigHandler
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if ($event->systemConfigOverrides === []) {
            return;
        }

        foreach ($event->systemConfigOverrides as $scope => $config) {
            $salesChannelId = $scope === 'default' ? null : $scope;

            foreach ($config as $key => $value) {
                $this->systemConfigService->set($key, $value, $salesChannelId, true);

                if ($salesChannelId === null) {
                    $event->io->info(\sprintf('Set system config "%s".', $key));
                } else {
                    $event->io->info(\sprintf('Set system config "%s" for sales channel "%s".', $key, $salesChannelId));
                }
            }
        }
    }
}
