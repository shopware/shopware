<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserEntity::class)]
class UserEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $user = new UserEntity();

        $historyEntries = new StateMachineHistoryCollection();
        $logEntries = new ImportExportLogCollection();
        $locale = new LocaleEntity();
        $avatar = new MediaEntity();
        $media = new MediaCollection();
        $accessKeys = new UserAccessKeyCollection();
        $configs = new UserConfigCollection();
        $recovery = new UserRecoveryEntity();
        $aclRoles = new AclRoleCollection();
        $createdOrders = new OrderCollection();
        $updatedOrders = new OrderCollection();
        $createdCustomers = new CustomerCollection();
        $updatedCustomers = new CustomerCollection();

        $user->setStateMachineHistoryEntries($historyEntries);
        $user->setImportExportLogEntries($logEntries);
        $user->setLocaleId('locale-id');
        $user->setAvatarId('avatar-id');
        $user->setUsername('admin');
        $user->setFirstName('Ada');
        $user->setLastName('Lovelace');
        $user->setEmail('ada@example.com');
        $user->setActive(true);
        $user->setLocale($locale);
        $user->setAvatarMedia($avatar);
        $user->setMedia($media);
        $user->setAccessKeys($accessKeys);
        $user->setConfigs($configs);
        $user->setRecoveryUser($recovery);
        $user->setAdmin(true);
        $user->setMcpAllowlist(['tool-a' => true]);
        $user->setAclRoles($aclRoles);
        $user->setTitle('Dr.');
        $user->setCreatedOrders($createdOrders);
        $user->setUpdatedOrders($updatedOrders);
        $user->setCreatedCustomers($createdCustomers);
        $user->setUpdatedCustomers($updatedCustomers);
        $user->setTimeZone('Europe/Berlin');

        static::assertSame($historyEntries, $user->getStateMachineHistoryEntries());
        static::assertSame($logEntries, $user->getImportExportLogEntries());
        static::assertSame('locale-id', $user->getLocaleId());
        static::assertSame('avatar-id', $user->getAvatarId());
        static::assertSame('admin', $user->getUsername());
        static::assertSame('Ada', $user->getFirstName());
        static::assertSame('Lovelace', $user->getLastName());
        static::assertSame('ada@example.com', $user->getEmail());
        static::assertTrue($user->getActive());
        static::assertSame($locale, $user->getLocale());
        static::assertSame($avatar, $user->getAvatarMedia());
        static::assertSame($media, $user->getMedia());
        static::assertSame($accessKeys, $user->getAccessKeys());
        static::assertSame($configs, $user->getConfigs());
        static::assertSame($recovery, $user->getRecoveryUser());
        static::assertTrue($user->isAdmin());
        static::assertSame(['tool-a' => true], $user->getMcpAllowlist());
        static::assertSame($aclRoles, $user->getAclRoles());
        static::assertSame('Dr.', $user->getTitle());
        static::assertSame($createdOrders, $user->getCreatedOrders());
        static::assertSame($updatedOrders, $user->getUpdatedOrders());
        static::assertSame($createdCustomers, $user->getCreatedCustomers());
        static::assertSame($updatedCustomers, $user->getUpdatedCustomers());
        static::assertSame('Europe/Berlin', $user->getTimeZone());
    }

    /**
     * @param \Closure(UserEntity): void $write
     * @param \Closure(UserEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $user = $this->userWithInternalProperties();
        $write($user);

        static::assertSame($expected, $read($user));
    }

    /**
     * @param \Closure(UserEntity): void $write
     * @param \Closure(UserEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $user = $this->userWithInternalProperties();
        $write($user);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, UserEntity::class));
        $read($user);
    }

    /**
     * @return \Generator<string, array{0: \Closure(UserEntity): void, 1: \Closure(UserEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (UserEntity $user) => $user->setPassword('secret'),
            static fn (UserEntity $user) => $user->getPassword(),
            'secret',
            'password',
        ];

        yield 'storeToken' => [
            static fn (UserEntity $user) => $user->setStoreToken('token'),
            static fn (UserEntity $user) => $user->getStoreToken(),
            'token',
            'storeToken',
        ];
    }

    private function userWithInternalProperties(): UserEntity
    {
        $user = new UserEntity();
        $user->internalSetEntityData(UserDefinition::ENTITY_NAME, new FieldVisibility(['password', 'storeToken']));

        return $user;
    }
}
