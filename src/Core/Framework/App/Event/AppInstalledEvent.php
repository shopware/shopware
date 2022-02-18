<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

class AppInstalledEvent extends ManifestChangedEvent
{
    public const NAME = 'app.installed';

    public function getName(): string
    {
        return self::NAME;
    }
}
