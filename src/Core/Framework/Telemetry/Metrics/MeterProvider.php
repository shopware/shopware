<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('core')]
class MeterProvider
{
    private static ?\Closure $meterProviderClosure = null;

    public static function bindMeter(ContainerInterface $container): void
    {
        self::$meterProviderClosure = static fn (): ?Meter => $container->has(Meter::class)
            ? $container->get(Meter::class)
            : null;
    }

    public static function meter(): ?Meter
    {
        return self::$meterProviderClosure ? \call_user_func(self::$meterProviderClosure) : null;
    }
}
