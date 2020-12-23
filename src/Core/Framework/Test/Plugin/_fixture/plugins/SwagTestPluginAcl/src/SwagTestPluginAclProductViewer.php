<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Shopware\Core\Framework\Plugin;

class SwagTestPluginAclProductViewer extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'product.viewer' => [
                'swag_demo_data:read',
            ],
        ];
    }
}
