<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\Storage\GlobalStorage;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(GlobalStorage::class)]
class GlobalStorageTest extends TestCase
{
    private GlobalStorage $storage;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->storage = new GlobalStorage($this->systemConfigService);
    }

    public function testCode(): void
    {
        static::assertSame('global', GlobalStorage::code());
    }

    public function testStatus(): void
    {
        $this->systemConfigService->set('core.consent.test-consent', ['status' => 'accepted']);

        $result = $this->storage->status('test-consent', 'user-123');

        static::assertSame('test-consent', $result->name);
        static::assertSame('user-123', $result->identifier);
        static::assertSame(ConsentState::ACCEPTED, $result->status);
    }

    public function testStatusIsRequestedWhenRecordDoesNotExist(): void
    {
        $result = $this->storage->status('test-consent', 'user-123');

        static::assertSame('test-consent', $result->name);
        static::assertSame('user-123', $result->identifier);
        static::assertSame(ConsentState::REQUESTED, $result->status);
    }

    public function testAccept(): void
    {
        $this->storage->accept('test-consent', 'user-123');

        $config = $this->systemConfigService->get('core.consent.test-consent');

        static::assertIsArray($config);
        static::assertSame('user-123', $config['user']);
        static::assertSame(ConsentState::ACCEPTED->value, $config['status']);
        static::assertArrayHasKey('timestamp', $config);
    }

    public function testRevoke(): void
    {
        $this->storage->revoke('test-consent', 'user-123');

        $config = $this->systemConfigService->get('core.consent.test-consent');

        static::assertIsArray($config);
        static::assertSame('user-123', $config['user']);
        static::assertSame(ConsentState::REVOKED->value, $config['status']);
        static::assertArrayHasKey('timestamp', $config);
    }

    public function testStatusThrowsExceptionWhenStatusKeyIsMissing(): void
    {
        $this->systemConfigService->set('core.consent.test-consent', ['user' => 'user-123']);

        $this->expectException(ConsentException::class);
        $this->expectExceptionMessage('Consent status is invalid.');

        $this->storage->status('test-consent', 'user-123');
    }
}
