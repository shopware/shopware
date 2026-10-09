<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppHandlerIdentifier
{
    private const PREFIX = 'app\\';

    public static function prefix(): string
    {
        return self::PREFIX;
    }

    public static function build(string $appName, string $identifier): string
    {
        return \sprintf('%s%s_%s', self::PREFIX, $appName, $identifier);
    }
}
