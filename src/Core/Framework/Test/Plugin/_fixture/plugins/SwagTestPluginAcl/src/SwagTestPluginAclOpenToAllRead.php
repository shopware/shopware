<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Shopware\Core\Framework\Plugin;

class SwagTestPluginAclOpenToAllRead extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'all' => [
                'open_to_all:read',
            ],
        ];
    }
}
