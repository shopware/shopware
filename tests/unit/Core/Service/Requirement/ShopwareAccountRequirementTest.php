<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;

/**
 * @internal
 */
#[CoversClass(ShopwareAccountRequirement::class)]
class ShopwareAccountRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('shopware_account', ShopwareAccountRequirement::getName());
    }

    public function testIsSatisfiedWhenUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn('1');

        $requirement = new ShopwareAccountRequirement($connection);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenNoUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn(false);

        $requirement = new ShopwareAccountRequirement($connection);

        static::assertFalse($requirement->isSatisfied());
    }
}
