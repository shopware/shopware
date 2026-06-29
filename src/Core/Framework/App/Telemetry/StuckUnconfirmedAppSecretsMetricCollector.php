<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;

/**
 * Reports how many apps have an unconfirmed secret (a "stuck" rotation), on the shared
 * `telemetry.collect_periodic_metrics` schedule. It only reports the number — it never changes any data. To
 * fix a stuck app, run `bin/console app:secret:recover <app>`.
 *
 * @internal
 */
#[Package('framework')]
class StuckUnconfirmedAppSecretsMetricCollector implements PeriodicMetricCollectorInterface
{
    public function __construct(private readonly AppSecretRotationService $rotationService)
    {
    }

    public function collect(): iterable
    {
        yield new ConfiguredMetric(
            'app.unconfirmed_app_secrets.count',
            $this->rotationService->countAppsWithUnconfirmedSecrets(Context::createCLIContext()),
        );
    }
}
