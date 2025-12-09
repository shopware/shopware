<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\Storage\AdminUserStorage;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(AdminUserStorage::class)]
class AdminUserStorageTest extends TestCase
{
    private AdminUserStorage $storage;

    /**
     * @var StaticEntityRepository<UserConfigCollection>
     */
    private StaticEntityRepository $userConfigRepository;

    protected function setUp(): void
    {
        $this->userConfigRepository = new StaticEntityRepository([]);
        $this->storage = new AdminUserStorage($this->userConfigRepository);
    }

    public function testCode(): void
    {
        static::assertSame('admin_user', AdminUserStorage::code());
    }

    public function testStatusReturnsRequestedWhenNoConfigExists(): void
    {
        $this->userConfigRepository->addSearch(new UserConfigCollection());

        $result = $this->storage->status('test-consent', 'user-123');

        static::assertSame('test-consent', $result->name);
        static::assertSame('user-123', $result->identifier);
        static::assertSame(ConsentState::REQUESTED, $result->status);
    }

    public function testStatusReturnsAcceptedWhenConfigExists(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('test-id');
        $userConfig->setValue(['_value' => 'accepted']);

        $this->userConfigRepository->addSearch(new UserConfigCollection([$userConfig]));

        $result = $this->storage->status('test-consent', 'user-456');

        static::assertSame('test-consent', $result->name);
        static::assertSame('user-456', $result->identifier);
        static::assertSame(ConsentState::ACCEPTED, $result->status);
    }

    public function testAcceptWithoutExistingConfig(): void
    {
        $this->userConfigRepository->addSearch([]);

        $this->storage->accept('test-consent', 'user-123');

        static::assertCount(1, $this->userConfigRepository->upserts);
        $upsert = $this->userConfigRepository->upserts[0][0];
        static::assertIsString($upsert['id']);
        static::assertSame('user-123', $upsert['userId']);
        static::assertSame('core.consent.test-consent', $upsert['key']);
        static::assertSame(['_value' => 'accepted'], $upsert['value']);
    }

    public function testAcceptWithExistingConfig(): void
    {
        $this->userConfigRepository->addSearch(['existing-id']);

        $this->storage->accept('test-consent', 'user-123');

        static::assertCount(1, $this->userConfigRepository->upserts);
        $upsert = $this->userConfigRepository->upserts[0][0];
        static::assertSame('existing-id', $upsert['id']);
        static::assertSame('user-123', $upsert['userId']);
        static::assertSame('core.consent.test-consent', $upsert['key']);
        static::assertSame(['_value' => 'accepted'], $upsert['value']);
    }

    public function testRevokeWithoutExistingConfig(): void
    {
        $this->userConfigRepository->addSearch([]);

        $this->storage->revoke('test-consent', 'user-789');

        static::assertCount(1, $this->userConfigRepository->upserts);
        $upsert = $this->userConfigRepository->upserts[0][0];
        static::assertIsString($upsert['id']);
        static::assertSame('user-789', $upsert['userId']);
        static::assertSame('core.consent.test-consent', $upsert['key']);
        static::assertSame(['_value' => 'revoked'], $upsert['value']);
    }

    public function testRevokeWithExistingConfig(): void
    {
        $this->userConfigRepository->addSearch(['existing-id']);

        $this->storage->revoke('test-consent', 'user-789');

        static::assertCount(1, $this->userConfigRepository->upserts);
        $upsert = $this->userConfigRepository->upserts[0][0];
        static::assertSame('existing-id', $upsert['id']);
        static::assertSame('user-789', $upsert['userId']);
        static::assertSame('core.consent.test-consent', $upsert['key']);
        static::assertSame(['_value' => 'revoked'], $upsert['value']);
    }
}
