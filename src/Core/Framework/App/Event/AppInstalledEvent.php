<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 */
#[Package('core')]
class AppInstalledEvent extends ManifestChangedEvent
{
    public const NAME = 'app.installed';

    public function getName(): string
    {
        return self::NAME;
    }
}
