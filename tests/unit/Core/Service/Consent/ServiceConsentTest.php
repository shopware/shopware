<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Consent\ServiceConsent;
use Shopware\Core\Service\ServiceConsentRevisionProvider;
use Shopware\Core\System\Consent\ConsentScope\System;

/**
 * @internal
 */
#[CoversClass(ServiceConsent::class)]
class ServiceConsentTest extends TestCase
{
    public function testDefinition(): void
    {
        $consent = new ServiceConsent($this->createMock(ServiceConsentRevisionProvider::class));

        static::assertSame('service_consent', $consent->getName());
        static::assertSame(System::NAME, $consent->getScopeName());
        static::assertEquals(new \DateTimeImmutable('2026-05-05'), $consent->getSince());
        static::assertSame(['system.system_config', 'system.plugin_maintain'], $consent->getRequiredPermissions());
    }

    public function testLatestRevisionIsFetchedFromRevisionProvider(): void
    {
        $revisionProvider = $this->createMock(ServiceConsentRevisionProvider::class);
        $revisionProvider->expects($this->once())
            ->method('getLatestRevision')
            ->willReturn('2026-06-01');

        $consent = new ServiceConsent($revisionProvider);

        static::assertSame('2026-06-01', $consent->getLatestRevision());
    }
}
