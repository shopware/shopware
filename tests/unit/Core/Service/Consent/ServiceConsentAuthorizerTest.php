<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Consent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Service\Consent\ServiceConsent;
use Shopware\Core\Service\Consent\ServiceConsentAuthorizer;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[CoversClass(ServiceConsentAuthorizer::class)]
class ServiceConsentAuthorizerTest extends TestCase
{
    public function testSupportsServiceConsentEvents(): void
    {
        $authorizer = new ServiceConsentAuthorizer($this->createMock(Connection::class));

        static::assertTrue($authorizer->supports(new ConsentAcceptedEvent(ServiceConsent::NAME, 'system', 'system', 'actor', '2026-05-05')));
        static::assertTrue($authorizer->supports(new ConsentRevokedEvent(ServiceConsent::NAME, 'system', 'system', 'actor')));
    }

    public function testDoesNotSupportOtherConsents(): void
    {
        $authorizer = new ServiceConsentAuthorizer($this->createMock(Connection::class));

        static::assertFalse($authorizer->supports(new ConsentAcceptedEvent('backend_data', 'system', 'system', 'actor')));
        static::assertFalse($authorizer->supports(new ConsentRevokedEvent('product_analytics', 'admin_user', 'user-1', 'actor')));
    }

    public function testAllowsServiceApps(): void
    {
        $appId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(static::anything(), ['id' => Uuid::fromHexToBytes($appId)])
            ->willReturn('1');

        $authorizer = new ServiceConsentAuthorizer($connection);

        static::assertTrue($authorizer->isAllowed($this->event(), $appId, new AclPrivilegeCollection([])));
    }

    public function testDeniesNonServiceApps(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('0');

        $authorizer = new ServiceConsentAuthorizer($connection);

        static::assertFalse($authorizer->isAllowed($this->event(), Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    public function testDeniesUnknownApp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $authorizer = new ServiceConsentAuthorizer($connection);

        static::assertFalse($authorizer->isAllowed($this->event(), Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    private function event(): ConsentAcceptedEvent
    {
        return new ConsentAcceptedEvent(ServiceConsent::NAME, 'system', 'system', 'actor', '2026-05-05');
    }
}
