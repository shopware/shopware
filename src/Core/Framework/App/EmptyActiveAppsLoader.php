<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

class EmptyActiveAppsLoader extends ActiveAppsLoader
{
    public function __construct()
    {
    }

    public function getActiveApps(): array
    {
        return [];
    }

    public function resetActiveApps(): void
    {
    }
}
