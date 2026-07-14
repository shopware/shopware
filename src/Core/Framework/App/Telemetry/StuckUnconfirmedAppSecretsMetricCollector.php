<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;

/**
 * Reports how many apps have an unconfirmed secret (a pending registration or rotation), on the shared
 * `telemetry.collect_periodic_metrics` schedule. It only reports the number — it never changes any data. To
 * repair a pending app, run `bin/console app:install <app>` or retry through the Administration installation API.
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
