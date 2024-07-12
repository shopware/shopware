<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\ServiceRegistryEntry;

/**
 * @internal
 */
#[CoversClass(ServiceRegistryEntry::class)]
class ServiceRegistryEntryTest extends TestCase
{
    public function testServiceRegistryEntry(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'My Cool Service', 'https://some-service.com', '/service/lifecycle/choose-app');

        static::assertEquals('MyCoolService', $entry->name);
        static::assertEquals('https://some-service.com', $entry->host);
        static::assertEquals('My Cool Service', $entry->description);
        static::assertEquals('/service/lifecycle/choose-app', $entry->appEndpoint);
    }
}
