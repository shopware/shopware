<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\SystemHeartbeatEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
#[CoversClass(SystemHeartbeatEvent::class)]
class SystemHeartbeatEventTest extends TestCase
{
    public function testIsAlwaysAllowedWithoutAnyPermissions(): void
    {
        $event = new SystemHeartbeatEvent();
        $permissions = new AclPrivilegeCollection([]);

        static::assertTrue($event->isAllowed('any-app-id', $permissions));
    }
}
