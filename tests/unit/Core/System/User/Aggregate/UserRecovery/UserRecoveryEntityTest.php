<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Aggregate\UserRecovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserRecoveryEntity::class)]
class UserRecoveryEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $user = new UserEntity();

        $recovery = new UserRecoveryEntity();
        $recovery->setUserId('user-id');
        $recovery->setUser($user);

        static::assertSame('user-id', $recovery->getUserId());
        static::assertSame($user, $recovery->getUser());
    }

    public function testHashIsReadableOutsideTwig(): void
    {
        $recovery = $this->recoveryWithInternalHash();
        $recovery->setHash('hash-value');

        static::assertSame('hash-value', $recovery->getHash());
    }

    public function testHashIsGuardedInsideTwig(): void
    {
        $recovery = $this->recoveryWithInternalHash();
        $recovery->setHash('hash-value');

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('hash', UserRecoveryEntity::class));
        $recovery->getHash();
    }

    private function recoveryWithInternalHash(): UserRecoveryEntity
    {
        $recovery = new UserRecoveryEntity();
        $recovery->internalSetEntityData(UserRecoveryDefinition::ENTITY_NAME, new FieldVisibility(['hash']));

        return $recovery;
    }
}
