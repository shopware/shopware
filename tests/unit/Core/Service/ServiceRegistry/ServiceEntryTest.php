<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ServiceRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceRegistry\ServiceStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceEntry::class)]
class ServiceEntryTest extends TestCase
{
    public function testServiceRegistryEntry(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'My Cool Service', 'https://some-service.com', '/service/lifecycle/choose-app', false, ServiceStatus::RETIRED);

        static::assertSame('MyCoolService', $entry->name);
        static::assertSame('https://some-service.com', $entry->host);
        static::assertSame('My Cool Service', $entry->description);
        static::assertSame('/service/lifecycle/choose-app', $entry->appEndpoint);
        static::assertFalse($entry->activateOnInstall);
        static::assertSame(ServiceStatus::RETIRED, $entry->status);
    }
}
