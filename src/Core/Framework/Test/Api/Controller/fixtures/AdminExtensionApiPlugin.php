<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller\fixtures;

use Shopware\Core\Framework\Plugin;

class AdminExtensionApiPlugin extends Plugin
{
    public function getAdminBaseUrl(): ?string
    {
        return 'https://extension-api.test';
    }
}
