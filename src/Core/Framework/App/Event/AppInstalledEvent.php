<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class AppInstalledEvent extends ManifestChangedEvent
{
    final public const NAME = 'app.installed';

    public function getName(): string
    {
        return self::NAME;
    }
}
