<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Schema\Notification\ToolListChangedNotification;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
class McpToolListChangedNotifier
{
    public function notify(): void
    {
        if (\Fiber::getCurrent() === null) {
            return;
        }

        \Fiber::suspend([
            'type' => 'notification',
            'notification' => new ToolListChangedNotification(),
        ]);
    }
}
